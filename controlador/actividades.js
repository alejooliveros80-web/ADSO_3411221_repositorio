// Guarda localmente las inscripciones de demostración del asociado.
const CLAVE_INSCRIPCIONES = "inscripcionesActividades";

// Convierte los valores recibidos en texto seguro antes de mostrarlos en la tabla.
function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[caracter]);
}

// Lee las inscripciones que se guardaron en el navegador actual.
function obtenerInscripciones() {
    try {
        return JSON.parse(localStorage.getItem(CLAVE_INSCRIPCIONES)) || [];
    } catch (error) {
        console.error("No se pudieron leer las inscripciones:", error);
        return [];
    }
}

// Envía los datos del formulario al PHP para guardarlos en MySQL.
const formularioActividad = document.getElementById("formularioActividad");
if (formularioActividad) {
    formularioActividad.addEventListener("submit", async evento => {
        evento.preventDefault();

        const boton = formularioActividad.querySelector('button[type="submit"]');
        const mensaje = document.getElementById("mensajeActividad");
        boton.disabled = true;
        mensaje.textContent = "Guardando actividad...";

        try {
            const respuesta = await fetch(formularioActividad.action, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(Object.fromEntries(new FormData(formularioActividad).entries()))
            });
            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                mensaje.textContent = resultado.mensaje || "No se pudo guardar la actividad.";
                return;
            }

            // Abre la lista; esa vista consultará los datos recién guardados en MySQL.
            window.location.href = "actividades.html";
        } catch (error) {
            console.error("Error al guardar la actividad:", error);
            mensaje.textContent = "No se pudo conectar con el servidor.";
        } finally {
            boton.disabled = false;
        }
    });
}

// Consulta la tabla de MySQL y crea una fila por cada actividad disponible.
const listaActividades = document.getElementById("listaActividades");
if (listaActividades) {
    const mensajeVacio = document.getElementById("mensajeVacio");
    const mensajeInscripcion = document.getElementById("mensajeInscripcion");
    const usuario = {
        logueado: localStorage.getItem("logueado") === "true",
        rol: (localStorage.getItem("rol") || "").toLowerCase(),
        nombre: localStorage.getItem("nombre") || "",
        email: (localStorage.getItem("email") || "").trim().toLowerCase()
    };
    let actividadesActuales = [];

    // Solo permite ver esta vista si el usuario ya inició sesión.
    if (!usuario.logueado) {
        window.location.href = "login.html";
    }

    // Dibuja las actividades y el estado de inscripción del asociado actual.
    function mostrarActividades() {
        const inscripciones = obtenerInscripciones();
        mensajeVacio.hidden = actividadesActuales.length > 0;

        listaActividades.innerHTML = actividadesActuales.map(actividad => {
            const idActividad = String(actividad.idActividad);
            const inscritos = inscripciones.filter(item => String(item.idActividad) === idActividad);
            const yaInscrito = inscritos.some(item => item.email === usuario.email);
            const puedeInscribirse = usuario.rol === "asociado" && usuario.email && !yaInscrito;
            const textoBoton = yaInscrito ? "Inscrito" :
                usuario.rol !== "asociado" ? "Solo asociados" : "Inscribirme";

            return `
                <tr>
                    <td>${escaparHTML(actividad.nombreActividad)}</td>
                    <td>${escaparHTML(actividad.fechaEvento)}</td>
                    <td>${escaparHTML(actividad.municipio)}</td>
                    <td>$${escaparHTML(actividad.totalRecaudado)}</td>
                    <td>${inscritos.length}</td>
                    <td><button class="enroll-button" type="button" data-actividad-id="${escaparHTML(idActividad)}" ${puedeInscribirse ? "" : "disabled"}>${textoBoton}</button></td>
                </tr>
            `;
        }).join("");
    }

    // Carga la lista desde el endpoint PHP conectado con la base de datos.
    async function cargarActividades() {
        try {
            const respuesta = await fetch("../modelo/listarActividades.php");
            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.success) {
                throw new Error(resultado.mensaje || "No se pudieron cargar las actividades.");
            }

            actividadesActuales = resultado.actividades;
            mostrarActividades();
        } catch (error) {
            console.error("Error al consultar las actividades:", error);
            mensajeVacio.textContent = "No se pudieron cargar las actividades desde el servidor.";
            mensajeVacio.hidden = false;
        }
    }

    // Registra una inscripción del asociado en la actividad seleccionada.
    listaActividades.addEventListener("click", evento => {
        const boton = evento.target.closest(".enroll-button");
        if (!boton || boton.disabled) return;

        if (!usuario.logueado) {
            window.location.href = "login.html";
            return;
        }

        if (!usuario.email || usuario.rol !== "asociado") {
            mensajeInscripcion.textContent = "Inicia sesión con una cuenta de asociado para inscribirte.";
            mensajeInscripcion.hidden = false;
            return;
        }

        const idActividad = Number(boton.dataset.actividadId);
        const inscripciones = obtenerInscripciones();
        const yaInscrito = inscripciones.some(item =>
            Number(item.idActividad) === idActividad && item.email === usuario.email
        );

        if (yaInscrito) {
            mensajeInscripcion.textContent = "Ya estás inscrito en esta actividad.";
            mensajeInscripcion.hidden = false;
            return;
        }

        inscripciones.push({
            idActividad,
            email: usuario.email,
            nombre: usuario.nombre,
            fechaInscripcion: new Date().toISOString()
        });

        try {
            localStorage.setItem(CLAVE_INSCRIPCIONES, JSON.stringify(inscripciones));
            mensajeInscripcion.textContent = "Tu inscripción quedó registrada en este navegador.";
            mensajeInscripcion.hidden = false;
            mostrarActividades();
        } catch (error) {
            console.error("No se pudo guardar la inscripción:", error);
            mensajeInscripcion.textContent = "No se pudo guardar la inscripción en este navegador.";
            mensajeInscripcion.hidden = false;
        }
    });

    cargarActividades();
}
