const formularioPago = document.getElementById("formularioPago");
const mensajePago = document.getElementById("mensajePago");

formularioPago.addEventListener("submit", async function (evento) {
    evento.preventDefault();

    const botonGuardar = formularioPago.querySelector('button[type="submit"]');

    // Prepara los datos que recibirá el PHP.
    const datos = {
        documentoAsociado: document.getElementById("documentoAsociado").value.trim(),
        idPrestamo: Number(document.getElementById("idPrestamo").value),
        fechaPago: document.getElementById("fechaPago").value,
        valorCuota: Number(document.getElementById("valorCuota").value)
    };

    botonGuardar.disabled = true;
    mensajePago.textContent = "Guardando pago...";

    try {
        const respuesta = await fetch("../modelo/guardarPago.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudo guardar el pago");
        }

        mensajePago.textContent = resultado.mensaje;
        formularioPago.reset();
    } catch (error) {
        mensajePago.textContent = error.message;
    } finally {
        botonGuardar.disabled = false;
    }
});