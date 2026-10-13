# Velvet Master — Fase 2: operacion interna

Fecha: 2026-10-12.

## Implementado

- Turnos programados para modelos y monitores en `operational_shifts`.
- Asistencia independiente en `shift_attendances`: entrada, salida, retraso, pausa y ausencia.
- Reserva de sala creada junto con el turno cuando se selecciona una sala; se bloquean cruces de horario para modelo, monitor y sala.
- Checklists de apertura y cierre asociados al turno y a la sala.
- Incidencias con severidad, estado, responsable, resolucion y trazabilidad.
- Solicitudes operativas con estado pendiente, aprobacion o rechazo.
- Novedades operativas vinculables a una modelo, turno o incidencia.
- Panel administrativo `/admin/operations` y visita guiada contextual.

## Separacion de datos

Un turno es la planificacion. La asistencia registra lo que realmente ocurrio. La reserva de sala controla el recurso fisico. Las futuras sesiones y transmisiones de Fase 4 se relacionaran con el turno, pero no se usan para calcular asistencia ni para duplicar horas.

## Roles

- Manager / Coordinador: programa turnos, registra asistencia, completa checklists, atiende incidencias, solicitudes y novedades.
- Monitor: conserva el permiso de crear incidencias; su alcance de modelos permanece restringido a asignaciones activas.
- Modelo: puede crear solicitudes operativas propias mediante el endpoint de portal autorizado.

## Verificacion

`StudioOperationsTest` cubre programacion con sala/checklists, bloqueo de solapamientos, asistencia y solicitud de una modelo activa.

## Pendiente de la siguiente iteracion de operacion

- Exponer el formulario de solicitudes tambien dentro del portal visual de modelo.
- Agregar alertas en tiempo real y filtros historicos para incidencias y novedades.
- Incorporar reglas de capacidad por sala si una sede requiere mas de una persona por recurso.
