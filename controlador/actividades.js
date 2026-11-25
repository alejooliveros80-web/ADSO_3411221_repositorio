const listaActividades = document.getElementById("listaActividades");
const botonNueva = document.getElementById("nuevaActividad");
const formularioActividad = document.getElementById("formularioActividad");
const elementoModalActividad = document.getElementById("modalActividad");
const modalActividad = new bootstrap.Modal(elementoModalActividad);
const mensajeVacio = document.getElementById("mensajeVacio");
const mensajeActividad = document.getElementById("mensajeActividad");
const cancelarActividad = document.getElementById("cancelarActividad");

let esAdministrador = false;
let esAsociado = false;
let actividades = [];
let idActividadEditando = null; // Guarda el ID cuando el administrador abre una actividad para editarla.

async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: { 'Content-Type': 'application/json' }
    };

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../modelo/gestionarActividades.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = "login.html";
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.mensaje || 'Error en la solicitud');
    }

    return resultado;
}

async function cargarActividades() {
    try {
        const resultado = await solicitarApi('GET');

        const rolUsuario = String(resultado.rol || "").toLowerCase();
        esAdministrador = rolUsuario === "admin";
        esAsociado = rolUsuario === "asociado";
        configurarAcciones();
        actividades = resultado.actividades;
        mostrarActividades();
    } catch (error) {
        alert('Error al cargar actividades: ' + error.message);
    }
}

function mostrarActividades() {
    listaActividades.replaceChildren();

    mensajeVacio.hidden = actividades.length > 0;

    if (actividades.length === 0) {
        return;
    }

    actividades.forEach((actividad) => {
        const fila = document.createElement('tr');
        const celdaInscritos = crearCelda(actividad.cantidadInscritos ?? 0);
        const celdaInscripcion = document.createElement("td");

        if (esAdministrador) {
            const botonEditar = document.createElement("button");
            botonEditar.type = "button";
            botonEditar.className = "btn btn-warning btn-sm";
            botonEditar.textContent = "Editar";

            botonEditar.addEventListener("click", () => {
                abrirEditarActividad(actividad);
            });

            const botonEliminar = document.createElement("button");
            botonEliminar.type = "button";
            botonEliminar.className = "btn btn-danger btn-sm ms-2";
            botonEliminar.textContent = "Eliminar";

            botonEliminar.addEventListener("click", () => {
                eliminarActividad(actividad.idActividad);
            });

            celdaInscripcion.append(botonEditar, botonEliminar);

        } else if (esAsociado) {
            const botonInscribirse = document.createElement("button");
            botonInscribirse.type = "button";
            botonInscribirse.className = "enroll-button";
            botonInscribirse.textContent = "Inscribirme";

            botonInscribirse.addEventListener("click", () => {
                inscribirActividad(actividad.idActividad);
            });
            celdaInscripcion.append(botonInscribirse);

        } else {
            celdaInscripcion.textContent = "—";
        }

        fila.append(
            crearCelda(actividad.nombreActividad),
            crearCelda(actividad.fechaEvento),
            crearCelda(actividad.municipio),
            crearCelda(
                actividad.totalRecaudado === null
                    ? "0"
                    : Number(actividad.totalRecaudado).toLocaleString(
                        "es-CO",
                        { style: "currency", currency: "COP" }
                    )
            ),
            celdaInscritos,
            celdaInscripcion
        );

        listaActividades.append(fila);

    });
}

function crearCelda(texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    return celda;
}

async function inscribirActividad(idActividad) {
    const mensaje = document.getElementById("mensajeInscripcion");

    try {
        const respuesta = await fetch("../modelo/inscribirActividad.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ idActividad: idActividad })
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudo realizar la inscripción");
        }

        mensaje.textContent = resultado.mensaje;
        mensaje.hidden = false;

        await cargarActividades();
    } catch (error) {
        mensaje.textContent = error.message;
        mensaje.hidden = false;
    }
}

// Pide confirmación y solicita al servidor eliminar la actividad.
async function eliminarActividad(idActividad) {
    const confirmar = confirm("¿Estás seguro de eliminar esta actividad?");

    if (!confirmar) {
        return;
    }

    try {
        const resultado = await solicitarApi("DELETE", {
            idActividad: Number(idActividad)
        });

        alert(resultado.mensaje || "Actividad eliminada correctamente");
        await cargarActividades();
    } catch (error) {
        alert("No se pudo eliminar la actividad: " + error.message);
    }
}

// Carga los datos de la actividad seleccionada en el formulario.
function abrirEditarActividad(actividad) {
    idActividadEditando = actividad.idActividad;

    document.getElementById("nombreActividad").value = actividad.nombreActividad;
    document.getElementById("fechaEvento").value = actividad.fechaEvento;
    document.getElementById("municipio").value = actividad.municipio;
    document.getElementById("totalRecaudado").value = actividad.totalRecaudado;

    document.getElementById("tituloModalActividad").textContent = "Editar actividad";
    mensajeActividad.textContent = "";
    modalActividad.show();
}

function configurarAcciones() {
    botonNueva.hidden = !esAdministrador;
}

botonNueva.addEventListener("click", () => {
    if (!esAdministrador) {
        return;
    }

    formularioActividad.reset();
    idActividadEditando = null;
    document.getElementById("tituloModalActividad").textContent = "Nueva actividad";
    mensajeActividad.textContent = "";
    modalActividad.show();
});

cancelarActividad.addEventListener("click", () => {
    formularioActividad.reset();
    idActividadEditando = null;
    document.getElementById("tituloModalActividad").textContent = "Nueva actividad";
    mensajeActividad.textContent = "";
    modalActividad.hide();
});

formularioActividad.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const datos = {
        nombreActividad: document.getElementById("nombreActividad").value.trim(),
        fechaEvento: document.getElementById("fechaEvento").value,
        municipio: document.getElementById("municipio").value.trim(),
        totalRecaudado: Number(
            document.getElementById("totalRecaudado").value
        )
    };

    const metodo = idActividadEditando === null ? "POST" : "PUT";

    if (idActividadEditando !== null) {
        datos.idActividad = Number(idActividadEditando);
    }

    try {
        const resultado = await solicitarApi(metodo, datos);

        alert(resultado.mensaje || resultado.message || "Actividad guardada");
        formularioActividad.reset();
        idActividadEditando = null;
        document.getElementById("tituloModalActividad").textContent = "Nueva actividad";
        modalActividad.hide();
        await cargarActividades();
    } catch (error) {
        mensajeActividad.textContent = error.message;
    }
});

cargarActividades();