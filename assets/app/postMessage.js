import axios from "axios";

export const postMessage = async () => {
  const message = document.getElementById("messageInput").value;
  const response = await axios.post("/redis/publish/1", { message });
  //const response = await axios.post("/redis/consume/1", { message });
  console.log(response);
};

export const appendMessage = (message) => {
  const messages = document.getElementById("messages");
  const li = document.createElement("li");
  li.innerText = message;
  messages.appendChild(li);
}

