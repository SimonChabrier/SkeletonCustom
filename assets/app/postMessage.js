import axios from "axios";

export const postMessage = async () => {
  const message = document.getElementById("messageInput").value;
  const response = await axios.post("/redis/publish/1", { message });
  document.getElementById("messageInput").value = "";
};

// add observer objet on /redis/consume/1 to append message to messages list dynamically
export const observer = () => {
    const eventSource = new EventSource("https://127.0.0.1:8000/stream");
    console.log(eventSource);
    
    eventSource.onmessage = event => {
      console.log(event.data);
      const response = event.data;
      console.log(response);
      const messages = document.getElementById("messages");
      const li = document.createElement("li");
      li.innerText = response;
      messages.appendChild(li);
    }
}  





