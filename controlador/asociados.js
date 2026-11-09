const formulario = document.getElementById("FormRegistro");

formulario.addEventListener("submit", async function (event) {
    event.preventDefault();

    const btnGuardar = formulario.querySelector("button[type='submit']");
    const mensajeRegistro = document.getElementById("mensaje_registro");

    const asociado = Object.fromEntries(new FormData(formulario).entries());

    if (asociado.panswoard !== asociado.confirmarPanswoard) {
        mensajeRegistro.textContent = "Las contraseñas no coinciden";
        mensajeRegistro.style.color = "red";
        return;
    }

    delete asociado.confirmarPanswoard;


    btnGuardar.disabled = true;
    mensajeRegistro.textContent = "Guardando...";

    try {
        const response = await fetch("../modelo/guardarAsociados.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(asociado)
        });

        const resultado = await response.json();

        if (response.ok && resultado.success) {
            mensajeRegistro.textContent = resultado.mensaje;
            mensajeRegistro.style.color = "green";
            formulario.reset();
            setTimeout(() => {
                window.location.href = "login.html";
            }, 1500);
        } else {
            mensajeRegistro.textContent = resultado.mensaje || "Ocurrió un error al guardar";
            mensajeRegistro.style.color = "red";
        }
    } catch (error) {
        console.error("Error al guardar el asociado:", error);
        mensajeRegistro.textContent = "Ocurrió un error al conectar con el servidor";
        mensajeRegistro.style.color = "red";
    } finally {
        btnGuardar.disabled = false;
    }
});