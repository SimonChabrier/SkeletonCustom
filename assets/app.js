// any CSS you import will output into a single css file (app.css in this case)
// import './styles/app.css';

// start the Stimulus application
// import './bootstrap';

import { postMessage, observer } from "./app/postMessage";

observer()

document.getElementById('submitBtn').addEventListener('click', (e) => { 
    e.preventDefault(); 
    postMessage(); 
});
