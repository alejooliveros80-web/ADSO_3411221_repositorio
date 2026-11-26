const listaMisPrestamos = document.getElementById("listaMisPrestamos");
const listaMisPagos = document.getElementById("listaMisPagos");
const mensajePrestamos = document.getElementById("mensajePrestamos");
const mensajePagos = document.getElementById("mensajePagos");

// Agrega un valor a una celda de la tabla como texto.
function agregarCelda(fila, valor) {
    const celda = document.createElement("td");
    celda.textContent = valor;
    fila.appendChild(celda);
    return celda;
}

// Muestra la información de préstamos y pagos del asociado conectado.
async function cargarMisPagos() {
    try {
        const respuesta = await fetch("../modelo/misPagos.php");
        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudo cargar tu información");
        }

        listaMisPrestamos.replaceChildren();
        listaMisPagos.replaceChildren();

        if (resultado.prestamos.length === 0) {
            const fila = document.createElement("tr");
            const celda = agregarCelda(fila, "No tienes solicitudes ni préstamos registrados");
            celda.colSpan = 5;
            listaMisPrestamos.appendChild(fila);
        } else {
            resultado.prestamos.forEach(function (prestamo) {
                const fila = document.createElement("tr");

                agregarCelda(
                    fila,
                    new Intl.NumberFormat("es-CO", {
                        style: "currency",
                        currency: "COP"
                    }).format(Number(prestamo.valorPrestamo))
                );
                agregarCelda(fila, `${Number(prestamo.tasaInteres).toFixed(2)} %`);
                agregarCelda(fila, prestamo.numeroCuotas);
                agregarCelda(fila, prestamo.fechaPrestamo);
                agregarCelda(fila, prestamo.estado);

                listaMisPrestamos.appendChild(fila);
            });
        }

        if (resultado.pagos.length === 0) {
            const fila = document.createElement("tr");
            const celda = agregarCelda(fila, "Aún no tienes pagos registrados");
            celda.colSpan = 3;
            listaMisPagos.appendChild(fila);
        } else {
            resultado.pagos.forEach(function (pago) {
                const fila = document.createElement("tr");

                agregarCelda(fila, pago.idPrestamo);
                agregarCelda(
                    fila,
                    new Intl.NumberFormat("es-CO", {
                        style: "currency",
                        currency: "COP"
                    }).format(Number(pago.valorCuota))
                );
                agregarCelda(fila, pago.fechaPago);

                listaMisPagos.appendChild(fila);
            });
        }

        mensajePrestamos.textContent = "";
        mensajePagos.textContent = "";
    } catch (error) {
        mensajePrestamos.textContent = error.message;
        mensajePagos.textContent = error.message;
    }
}

cargarMisPagos();