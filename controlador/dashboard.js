document.addEventListener('DOMContentLoaded', () =>{
    const logueado = localStorage.getItem('logueado');

    if (logueado !== 'true'){
        window.location.href = 'login.html';
        return;
    }

    const nombre = localStorage.getItem('nombre');
    const rol = localStorage.getItem('rol')

    document.getElementById('nombreUsuario').textContent = nombre;

    if (rol === 'admin') {
        document.getElementById('tarjetaAdministrador').classList.remove('d-none');
        document.getElementById('tarjetaAsociado').classList.add('d-none');
    }else{
        document.getElementById('tarjetaAdministrador').classList.add('d-none');
        document.getElementById('tarjetaAsociado').classList.remove('d-none');
    }
});

