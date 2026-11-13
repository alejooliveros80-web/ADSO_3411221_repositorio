const lsitaActividades = document.getElementById('listaActividades');
let esAdministrador = false;
let actividades=[];

async function solicitarApi (metodo, datos) {
    const opciones = {
        method:  metodo,
        headers : {'Content-type': 'application/json'},
    }

    if (datos) {
        opciones.body = JSON.stringify(datos);
    }

    const respuesta = await fetch("../modelo/gestionarActividades.php", opciones);
    const resultado = await respuesta.json();

    if (respuesta.status === 401) {
    window.location.href = "login.html";
    }

    if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.mensaje || "Error en la solicitud");
    }

return resultado;
}

async function cargarActividades(){
    try{
        const resultado = await solicitarApi("GET");

        esAdministrador = resultado.rol === "admin";
        configurarAcciones();

        actividades =resultado.actividades;
        mostrarActividades();
    } catch (error) {
    alert("Error al cargar las actividades:" + error.message);
    }
}

    function mostrarActividades(){
        lsitaActividades.replaceChildren();

        if(actividades.length === 0){
            const fila = document.createElement("tr");
            const celda = document.createElement("td");
            celda.colSpan = esAdministrador ? 5:4;
            celda.className = "text-cent py-4 text-muted";
            celda.textContent = "No hay actividades disponibles.";
            fila.append(celda);
            lsitaActividades.append(fila);
            return;
        }

        actividades.forEach((actividad) => {
            const fila = document.createElement("tr");
            fila.append(
                crearCelda(actividad.nombreActividad),
                crearCelda(actividad.fechaEvento),
                crearCelda(actividad.municipio),
                crearCelda(actividad.totalRecaudado===null?"Pendiente": Number(actividad.totalRecaudado).toLocaleString("es-Co", { style: "currency", currency: "COP"})),
            );
            if(esAdministrador){
                const acciones = document.createElement("td");
                acciones.className="text-nowrap";

                const botonEditar = document.createElement("button");
                botonEditar.type = "button";
                botonEditar.className = "btn btn-sm btn-outline-primary me-2";
                botonEditar.innerHTML = '<i class="bi bi-pencil"></i>';
                botonEditar.addEventListener("click", ()=> abrirEdicion(actividad));

                const botonEliminar = document.createElement("button");
                botonEliminar.type = "button";
                botonEliminar.className = "btn btn-sm btn-outline-danger me-2";
                botonEliminar.innerHTML = '<i class="bi bi-trash3"></i>';
                botonEliminar.addEventListener("click", ()=> eliminarActividad(actividad));

                acciones.append(botonEditar, botonEliminar)
                fila.append(acciones);
            }
            lsitaActividades.append(fila);
        });
    }

    function crearCelda(texto){
        const celda = document.createElement("td")
        celda.textContent = texto;
        return celda;
    }

    cargarActividades();