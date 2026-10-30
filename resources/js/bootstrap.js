import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Axios lee XSRF-TOKEN de la cookie en cada petición. Laravel rota ese token
// al autenticar o cerrar sesión; fijar el del HTML inicial provoca errores 419.
