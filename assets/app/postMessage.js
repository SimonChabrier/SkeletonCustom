import axios from "axios";

export const postMessage = async () => {
  const message = document.getElementById("messageInput").value;
  const response = await axios.post("/redis/publish/1", { message });
  document.getElementById("messageInput").value = "";
};

// add observer objet on /redis/consume/1 to append message to messages list dynamically
export const observer = () => {
    const eventSource = new EventSource("https://127.0.0.1:8000/stream");
    //console.log(eventSource);
    
    eventSource.onmessage = event => {
      //console.log(event.data);
      console.log('event');
      const response = event.data;
      //console.log(response);
      const messages = document.getElementById("messages");
      const li = document.createElement("li");
      li.innerText = response;
      messages.appendChild(li);
    }
    // on relance tout de suite une nouvelle insatance de l'observer
    // si la connexion est fermée ce qui se produit après 60 secondes d'inactivité
    // quand aucun message n'est publié sur le canal ou quand le serveur est redémarré etc.
    eventSource.onerror = event => {
        if (event.target.readyState === EventSource.CLOSED) {
          console.log('La connexion a été fermée.');
          observer(); // Relance l'observation
        }
      }
  }





