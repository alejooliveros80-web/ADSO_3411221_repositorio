const formulario = document.getElementById("FormRegistro");
// Atiende el envío del formulario sin recargar la página.
formulario.addEventListener("submit", async function (e) {
    // Evita que el navegador envíe el formulario de manera tradicional.
    e.preventDefault();


    const boton = formulario.querySelector('button[type="submit"]');
    const mensaje = document.getElementById("mensajeLogin");


    // Desactiva el botón mientras se consulta el servidor.
    boton.disabled = true;

    try {
        // Envía correo y contraseña al archivo PHP como JSON.
        const respuesta = await fetch(formulario.action, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(Object.fromEntries(new FormData(formulario).entries()),
            ),
        });

        // Convierte la respuesta JSON del servidor en un objeto JavaScript.
        const resultado = await respuesta.json();

        // Muestra el error recibido si las credenciales no son válidas.
        if (!respuesta.ok || !resultado.success) {
            mensaje.textContent =
                resultado.mensaje || resultado.message || "Correo o contraseña incorrectos";
            return;
        }

        // Conserva los datos básicos para mostrar el panel del usuario.
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', resultado.rol);
        localStorage.setItem('nombre', resultado.nombre);

        // Abre el panel después de iniciar sesión correctamente.
        window.location.href = 'dashboard.html';

    } catch (error) {
        // Informa si no fue posible comunicarse con el servidor.
        console.error("Error al iniciar sesion:", error);
        mensaje.textContent =
            "Ocurrio un error al iniciar sesion. Por favor, intentelo de nuevo mas tarde.";
    } finally {
        // Vuelve a habilitar el botón al terminar la petición.
        boton.disabled = false;
    }
});
