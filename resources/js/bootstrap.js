import axios from 'axios';
import { installEnvelopeUnwrap } from './utils/apiEnvelope';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Every /api/* reply is {success, message, data}; hand the pages the shapes they were written for.
installEnvelopeUnwrap(window.axios);
