const listaAsociados = document.getElementById("listaAsociados");
const mensajeAsociados = document.getElementById("mensajeAsociados");
const panelFormulario = document.getElementById("panelFormulario");
const formularioAsociado = document.getElementById("formAsociado");
const inputContrasena = document.getElementById("panswoard");

// Consulta el endpoint de administración y convierte su respuesta JSON.
async function solicitarAsociados(metodo, datos = null) {
    const opciones = { method: metodo };

    if (datos !== null) {
        opciones.headers = { "Content-Type": "application/json" };
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../modelo/gestionarAsociados.php", opciones);
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.mensaje || "No se pudo completar la operación");
    }

    return resultado;
}

// Agrega valores a las celdas como texto para no interpretar datos como HTML.
function agregarCelda(fila, valor) {
    const celda = document.createElement("td");
    celda.textContent = valor ?? "";
    fila.appendChild(celda);
    return celda;
}

// Crea los botones de acciones para cada asociado de la tabla.
function crearBotonAccion(texto, clase, accion) {
    const boton = document.createElement("button");
    boton.type = "button";
    boton.className = clase;
    boton.textContent = texto;
    boton.addEventListener("click", accion);
    return boton;
}

// Muestra en la tabla los asociados devueltos por el servidor.
function mostrarAsociados(asociados) {
    listaAsociados.replaceChildren();

    if (asociados.length === 0) {
        const fila = document.createElement("tr");
        const celda = agregarCelda(fila, "No hay asociados registrados");
        celda.colSpan = 10;
        listaAsociados.appendChild(fila);
        return;
    }

    asociados.forEach(function (asociado) {
        const fila = document.createElement("tr");
        agregarCelda(fila, asociado.documento);
        agregarCelda(fila, asociado.nombres);
        agregarCelda(fila, asociado.apellidos);
        agregarCelda(fila, asociado.fechaNacimiento);
        agregarCelda(fila, asociado.genero);
        agregarCelda(fila, asociado.direccionResidencia);
        agregarCelda(fila, asociado.ciudad);
        agregarCelda(fila, asociado.telefono);
        agregarCelda(fila, asociado.email);

        const celdaAcciones = agregarCelda(fila, "");
        const acciones = document.createElement("div");
        acciones.className = "management-row-actions";
        acciones.appendChild(crearBotonAccion("Editar", "management-edit", () => editarAsociado(asociado)));
        acciones.appendChild(crearBotonAccion("Eliminar", "management-delete", () => eliminarAsociado(asociado.idAsociado)));
        celdaAcciones.appendChild(acciones);
        listaAsociados.appendChild(fila);
    });
}

// Lee la lista actualizada desde el servidor.
async function cargarAsociados() {
    mensajeAsociados.textContent = "Cargando asociados...";

    try {
        const resultado = await solicitarAsociados("GET");
        mostrarAsociados(resultado.asociados);
        mensajeAsociados.textContent = `${resultado.asociados.length} asociados registrados`;
    } catch (error) {
        mensajeAsociados.textContent = error.message;
    }
}

// Abre el formulario en modo edición y deja la contraseña opcional.
function editarAsociado(asociado) {
    document.getElementById("tituloFormulario").textContent = "Editar asociado";
    document.getElementById("notaContrasena").textContent = "Déjala vacía para conservar la contraseña actual.";
    document.getElementById("ayudaContrasena").textContent = "Actualiza los datos del asociado.";
    inputContrasena.required = false;

    Object.keys(asociado).forEach(function (campo) {
        const entrada = formularioAsociado.elements[campo];
        if (entrada) {
            entrada.value = asociado[campo] ?? "";
        }
    });

    inputContrasena.value = "";
    panelFormulario.hidden = false;
    panelFormulario.scrollIntoView({ behavior: "smooth" });
}

// Limpia el formulario y lo prepara para crear un asociado nuevo.
function prepararNuevoAsociado() {
    formularioAsociado.reset();
    document.getElementById("idAsociado").value = "";
    document.getElementById("tituloFormulario").textContent = "Crear asociado";
    document.getElementById("notaContrasena").textContent = "Obligatoria para crear una cuenta.";
    document.getElementById("ayudaContrasena").textContent = "La contraseña debe tener mínimo 8 caracteres.";
    inputContrasena.required = true;
    panelFormulario.hidden = false;
    panelFormulario.scrollIntoView({ behavior: "smooth" });
}

// Guarda datos nuevos o actualizados según el método HTTP.
formularioAsociado.addEventListener("submit", async function (evento) {
    evento.preventDefault();

    const datos = Object.fromEntries(new FormData(formularioAsociado).entries());
    const idAsociado = datos.idAsociado;
    const metodo = idAsociado ? "PUT" : "POST";

    try {
        const resultado = await solicitarAsociados(metodo, datos);
        panelFormulario.hidden = true;
        formularioAsociado.reset();
        await cargarAsociados();
        mensajeAsociados.textContent = resultado.mensaje;
    } catch (error) {
        mensajeAsociados.textContent = error.message;
    }
});

// Pide confirmación antes de borrar y luego actualiza la lista.
async function eliminarAsociado(idAsociado) {
    if (!window.confirm("¿Seguro que quieres eliminar este asociado?")) {
        return;
    }

    try {
        const resultado = await solicitarAsociados("DELETE", { idAsociado });
        await cargarAsociados();
        mensajeAsociados.textContent = resultado.mensaje;
    } catch (error) {
        mensajeAsociados.textContent = error.message;
    }
}

document.getElementById("botonNuevoAsociado").addEventListener("click", prepararNuevoAsociado);
document.getElementById("botonCancelar").addEventListener("click", () => {
    formularioAsociado.reset();
    panelFormulario.hidden = true;
});

cargarAsociados();
