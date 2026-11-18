const listActividades = document.getElementById('listActividades');
let esAdministrador = false;
let actividades = [];

async function solicitarApi(metodo, datos) {
    const opciones = {
        method: metodo,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch('/api/actividades', opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
        window.location.href = '/login';
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'Error en la solicitud');
    }

    return resultado;
}

async function cargarActividades() {
    try {
        const resultado = await solicitarApi('GET');
        esAdministrador = resultado.rol === 'admin';
        //configurarAcciones();
        actividades = resultado.actividades;
        mostrarActividades();

        actividades = resultado.actividades;
    } catch (error) {
        alert('Error al cargar actividades: ' + error.message);
    }

    function mostrarActividades() {
        listaActividades.replaceChildren();

        if (actividades.length === 0) {
            const fila = document.createElement('tr');
            const celda = document.createElement('td');
            celda.colSpan = esAdministrador ? 5 : 4;
            celda.className = "text-center py-4 text-muted";
            celda.textContent = 'No hay actividades disponibles';
            fila.append(celda);
            listaActividades.append(fila);
            return;
        }

        actividades.forEach((actividad) => {
            const fila = document.createElement('tr');
            fila.append(
                crearCelda(actividad.nombreActividad),
                crearCelda(actividad.FechaEvento),
                crearCelda(actividad.municipio),
                crearCelda(actividad.totalRecaudado === null ? '0' : Number(actividad.totalRecaudado).toLocaleString('es-ES', { style: 'currency', currency: 'COP' })),
            );
            if (esAdministrador) {
                const acciones = document.createElement('td');
                acciones.className = 'text-nowrap';

                const botonEditar = document.createElement('button');
                botonEditar.type = "button";
                botonEditar.className = "btn btn-sm btn-outline-primary me-2";
                botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
                botonEditar.addEventListener('click', () => abrirEdicion(actividad));

                const botonEliminar = document.createElement('button');
                botonEliminar.type = "button";
                botonEliminar.className = "btn btn-sm btn-outline-danger";
                botonEliminar.innerHTML = '<i class="bi bi-trash"></i>';
                botonEliminar.addEventListener('click', () => eliminarActividad(actividad));

                acciones.append(botonEditar, botonEliminar);
                fila.append(acciones);
            }

            listaActividades.append(fila);

        });
    }

    function crearCelda(texto) {
        const celda = document.createElement("td");
        celda.textContent = texto;
        return celda;
    }


}