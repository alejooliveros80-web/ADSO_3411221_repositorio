document.addEventListener('DOMContentLoaded', () => {
    const logueado = localStorage.getItem('logueado');

    if (logueado !== 'true') {
        window.location.href = '../vista/login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = (localStorage.getItem('rol') || '').toLowerCase();

    document.getElementById('nombreUsuario').textContent = nombre;

    if (rol === 'admin') {
        document.getElementById('tarjetaAdministrador').classList.remove('d-none');
        document.getElementById('tarjetaAsociado').classList.add('d-none');
    } else {
        document.getElementById('tarjetaAdministrador').classList.add('d-none');
        document.getElementById('tarjetaAsociado').classList.remove('d-none');
    }
});

async function cerrarSesion() {
    try {
        const respuesta = await  fetch("../modelo/cerrarSesion.php", {
            method: "POST",
        });
        
        if(!respuesta.ok){
            throw new Error("Error al cerrar sesion"); 
        }
        // Borra solo los datos de sesión y conserva actividades e inscripciones.
        ['logueado', 'rol', 'nombre', 'email'].forEach(clave => localStorage.removeItem(clave));
        window.location.href = "../vista/login.html";
    } catch (error) {
        console.error("Error al cerrar sesion:", error);
    }
}
