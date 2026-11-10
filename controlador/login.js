const formulario = document.getElementById("FormRegistro");
formulario.addEventListener("submit", async function (e) {
    e.preventDefault();


    const boton = formulario.querySelector('button[type="submit"]');
    const mensaje = document.getElementById("mensajeLogin");


    boton.disabled = true;

    try {
        const respuesta = await fetch(formulario.action, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(Object.fromEntries(new FormData(formulario).entries()),
            ),
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || !resultado.success) {
            mensaje.textContent =
                resultado.mensaje || resultado.message || "Correo o contraseña incorrectos";
            return;
        }

        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', resultado.rol);
        localStorage.setItem('nombre', resultado.nombre);

        window.location.href = 'dashboard.html';

    } catch (error) {
        console.error("Error al iniciar sesion:", error);
        mensaje.textContent =
            "Ocurrio un error al iniciar sesion. Por favor, intentelo de nuevo mas tarde.";
    } finally {
        boton.disabled = false;
    }
});