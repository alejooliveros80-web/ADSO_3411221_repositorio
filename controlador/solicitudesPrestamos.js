const listaSolicitudes = document.getElementById("listaSolicitudes");
const mensajeSolicitudes = document.getElementById("mensajeSolicitudes");

// Agrega texto a la tabla como contenido, sin interpretarlo como HTML.
function agregarCelda(fila, texto) {
    const celda = document.createElement("td");
    celda.textContent = texto;
    fila.appendChild(celda);
    return celda;
}

// Carga las solicitudes pendientes desde el PHP.
async function cargarSolicitudes(mostrarCarga = true) {
    if (mostrarCarga) {
        mensajeSolicitudes.textContent = "Cargando solicitudes...";
    }

    try {
        const respuesta = await fetch("../modelo/gestionarPrestamos.php");
        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudieron cargar las solicitudes");
        }

        listaSolicitudes.replaceChildren();

        if (resultado.solicitudes.length === 0) {
            const fila = document.createElement("tr");
            const celda = agregarCelda(fila, "No hay solicitudes pendientes");
            celda.colSpan = 7;
            listaSolicitudes.appendChild(fila);
            mensajeSolicitudes.textContent = "";
            return;
        }

        resultado.solicitudes.forEach(function (solicitud) {
            const fila = document.createElement("tr");

            agregarCelda(fila, `${solicitud.nombres} ${solicitud.apellidos}`);
            agregarCelda(fila, solicitud.documento);
            agregarCelda(
                fila,
                new Intl.NumberFormat("es-CO", {
                    style: "currency",
                    currency: "COP"
                }).format(Number(solicitud.valorPrestamo))
            );
            agregarCelda(fila, `${Number(solicitud.tasaInteres).toFixed(2)} %`);
            agregarCelda(fila, solicitud.numeroCuotas);
            agregarCelda(fila, solicitud.fechaPrestamo);

            const celdaAcciones = document.createElement("td");

            const botonAprobar = document.createElement("button");
            botonAprobar.type = "button";
            botonAprobar.className = "button-primary";
            botonAprobar.textContent = "Aprobar";
            botonAprobar.addEventListener("click", function () {
                decidirSolicitud(solicitud.idPrestamo, "Aprobado", celdaAcciones);
            });

            const botonRechazar = document.createElement("button");
            botonRechazar.type = "button";
            botonRechazar.className = "button-secondary";
            botonRechazar.textContent = "Rechazar";
            botonRechazar.addEventListener("click", function () {
                decidirSolicitud(solicitud.idPrestamo, "Rechazado", celdaAcciones);
            });

            celdaAcciones.append(botonAprobar, botonRechazar);
            fila.appendChild(celdaAcciones);
            listaSolicitudes.appendChild(fila);
        });

        if (mostrarCarga) {
            mensajeSolicitudes.textContent = "";
        }
    } catch (error) {
        mensajeSolicitudes.textContent = error.message;
    }
}

// Envía la decisión del administrador al PHP.
async function decidirSolicitud(idPrestamo, decision, celdaAcciones) {
    celdaAcciones.querySelectorAll("button").forEach(function (boton) {
        boton.disabled = true;
    });

    try {
        const respuesta = await fetch("../modelo/gestionarPrestamos.php", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ idPrestamo, decision })
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudo procesar la solicitud");
        }

        await cargarSolicitudes(false);
        mensajeSolicitudes.textContent = resultado.mensaje;
    } catch (error) {
        mensajeSolicitudes.textContent = error.message;
        celdaAcciones.querySelectorAll("button").forEach(function (boton) {
            boton.disabled = false;
        });
    }
}

cargarSolicitudes();