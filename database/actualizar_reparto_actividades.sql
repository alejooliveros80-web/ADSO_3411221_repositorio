-- Agrega los campos para guardar la parte del club y el cierre del reparto.
ALTER TABLE actividades
    ADD COLUMN valorClub DECIMAL(12,2) NULL DEFAULT NULL,
    ADD COLUMN fechaDistribucion DATETIME NULL DEFAULT NULL;

-- Guarda el valor asignado a cada asociado inscrito en la actividad.
ALTER TABLE actividades_asociado
    ADD COLUMN valorRepartido DECIMAL(12,2) NULL DEFAULT NULL;
