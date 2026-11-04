const usuarios = [
    {usuario: 'admin@gmail.com', clave: '123', rol:'admin', nombre:'admin MR'},
    {usuario: 'juanpe@gmail.com', clave: '123', rol: 'asociado', nombre: 'Juan Perez'}
];

document.getElementById('FormRegistro').addEventListener('submit', function (e) {
    e.preventDefault();

    const usuarioImput = document.getElementById('email').value; 
    const claveImput = document.getElementById('contrasena').value;

    const usuarioEncontrado = usuarios.find(
        u => u.usuario === usuarioImput && u.clave === claveImput
    );

    if(usuarioEncontrado){
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', usuarioEncontrado.rol);
        localStorage.setItem('nombre', usuarioEncontrado.nombre);
        window.location.href = 'dashboard.html';
    }else{
        alert('Usuario o contraseña incorrectos');
    }
});