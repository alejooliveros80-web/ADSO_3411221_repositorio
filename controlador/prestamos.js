const formularioPrestamo = document.getElementById("formularioPrestamo");
const mensajePrestamo = document.getElementById("mensajePrestamo");

formularioPrestamo.addEventListener("submit", async function (evento) {
    evento.preventDefault();

    const botonGuardar = formularioPrestamo.querySelector('button[type="submit"]');

    // Prepara los datos con los nombres que usaremos en PHP y MySQL.
    const datos = {
        fechaPrestamo: document.getElementById("fechaSolicitud").value,
        valorPrestamo: Number(document.getElementById("monto").value),
        tasaInteres: Number(document.getElementById("tasa").value),
        numeroCuotas: Number(document.getElementById("plazo").value)
    };

    botonGuardar.disabled = true;
    mensajePrestamo.textContent = "Guardando préstamo...";

    try {
        const respuesta = await fetch("../modelo/guardarPrestamo.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudo guardar el préstamo");
        }

        mensajePrestamo.textContent = resultado.mensaje;
        formularioPrestamo.reset();
    } catch (error) {
        mensajePrestamo.textContent = error.message;
    } finally {
        botonGuardar.disabled = false;
    }
});