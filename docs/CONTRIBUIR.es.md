# Cómo contribuir a The Velvet Studio

Gracias por ayudar a mejorar The Velvet Studio. Esta guía explica cómo proponer
cambios seguros, claros y fáciles de revisar.

English version: [CONTRIBUTING.md](../CONTRIBUTING.md).

## Antes de comenzar

- Lee la guía funcional en [guia-plataforma.md](guia-plataforma.md).
- Sigue las convenciones de [AGENTS.md](../AGENTS.md), especialmente la
  obligación de incluir guía Driver.js en flujos nuevos para usuarios.
- Nunca subas `.env`, credenciales, llaves API, datos personales, archivos
  cargados por usuarios ni exportaciones de producción.

## Preparación local

Requisitos: PHP 8.2 o superior, Composer, Node.js 22 o superior y una base de
datos configurada.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run dev
```

En macOS o Linux usa `cp` en lugar de `copy`.

Para crear las cuentas de prueba:

```bash
php artisan db:seed --class=TestUsersSeeder
```

## Flujo para contribuir

1. Crea una rama enfocada desde la rama principal actual.
2. Mantén el cambio acotado y explica su impacto funcional en el Pull Request.
3. Crea o actualiza pruebas de feature/regresión si cambias permisos,
   transiciones de estado, enlaces públicos, persistencia de datos o corriges
   un error.
4. Crea o actualiza la guía contextual Driver.js para cada módulo, flujo o
   componente relevante que agregues.
5. Actualiza `docs/guia-plataforma.md` si cambia la operación del equipo.
6. Ejecuta las validaciones antes de abrir el Pull Request.

```bash
php artisan test
npm run build
```

## Lista de verificación para Pull Request

- [ ] El cambio tiene un propósito claro y único.
- [ ] Se revisaron autorización y validaciones.
- [ ] Se validó la vista responsive sin solapamientos.
- [ ] Las pruebas cubren el flujo crítico o la regresión.
- [ ] Se agregó guía Driver.js o se indicó por qué no aplica.
- [ ] No se incluyeron secretos, datos productivos ni archivos generados de
      `public/build`.
- [ ] Se actualizó la documentación si cambió un flujo del equipo.

## Integración continua

GitHub Actions se ejecuta con cada `push` y Pull Request:

- **Backend · PHP 8.2** valida Composer y ejecuta `php artisan test` con SQLite
  aislado en memoria.
- **Frontend · Node 22** instala dependencias bloqueadas y ejecuta
  `npm run build`.

Con protección de rama, ambos checks deben aprobar antes de fusionar cambios.

## Reporte de errores y seguridad

Usa un issue para errores reproducibles no sensibles e incluye pasos,
resultado esperado, resultado real y capturas cuando ayuden.

No publiques credenciales, información personal, enlaces firmados ni
vulnerabilidades de seguridad en issues públicos. Consulta [SECURITY.md](../SECURITY.md)
y contacta en privado a una persona mantenedora para reportes de seguridad.

## Licencia y marca

Las contribuciones de código se distribuyen bajo la [licencia MIT](../LICENSE).
El nombre, logos y demás activos de The Velvet Studio no se licencian mediante
MIT; revisa [TRADEMARKS.md](../TRADEMARKS.md).
