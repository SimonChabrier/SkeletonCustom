// any CSS you import will output into a single css file (app.css in this case)
// import './styles/app.css';

// start the Stimulus application
// import './bootstrap';

import { postMessage, observer } from "./app/postMessage";


// on initialise l'observer dès le chargement de la page
document.addEventListener('DOMContentLoaded', () => {
 observer()
 console.log('js started');
});

document.getElementById('submitBtn').addEventListener('click', (e) => { 
    e.preventDefault(); 
    postMessage(); 
});

console.log('Hello Webpack Encore! Edit me in assets/app.js');