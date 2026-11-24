const listaActividades = document.getElementById("listaActividades");
const botonNueva = document.getElementById("nuevaActividad");
const formularioActividad = document.getElementById("formularioActividad");
const seccionFormularioActividad = document.getElementById("formularioActividadSeccion");
const mensajeVacio = document.getElementById("mensajeVacio");
const mensajeActividad = document.getElementById("mensajeActividad");
const cancelarActividad = document.getElementById("cancelarActividad");

let esAdministrador = false;
let actividades = [];

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

        esAdministrador = String(resultado.rol).toLowerCase() === "admin";
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
            celdaInscripcion.textContent = "—";
        } else {
            const botonInscribirse = document.createElement("button");
            botonInscribirse.type = "button";
            botonInscribirse.className = "enroll-button";
            botonInscribirse.textContent = "Inscribirme";

            botonInscribirse.addEventListener("click", () => {
                inscribirActividad(actividad.idActividad);
            });

            celdaInscripcion.append(botonInscribirse);
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

function configurarAcciones() {
    botonNueva.hidden = !esAdministrador;
}

botonNueva.addEventListener("click", () => {
    if (!esAdministrador) {
        return;
    }

    formularioActividad.reset();
    mensajeActividad.textContent = "";
    seccionFormularioActividad.hidden = false;
});

cancelarActividad.addEventListener("click", () => {
    formularioActividad.reset();
    mensajeActividad.textContent = "";
    seccionFormularioActividad.hidden = true;
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

    try {
        const resultado = await solicitarApi("POST", datos);

        alert(resultado.mensaje || resultado.message || "Actividad guardada");
        formularioActividad.reset();
        seccionFormularioActividad.hidden = true;

        await cargarActividades();
    } catch (error) {
        mensajeActividad.textContent = error.message;
    }
});

cargarActividades();