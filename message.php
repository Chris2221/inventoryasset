<style>
    /* Floating Action Button (FAB) for Chat */
    .chat-fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        font-size: 1.75rem;
        z-index: 1056;
        transition: transform 0.2s ease-in-out, opacity 0.3s, visibility 0.3s;
    }

    .chat-fab:hover {
        transform: scale(1.1);
    }

    .chat-fab.hidden {
        transform: scale(0.5);
        opacity: 0;
        visibility: hidden;
    }

    /* Chatbox Widget */
    .chatbox-widget {
        position: fixed;
        bottom: 6.5rem;
        /* Position above the FAB */
        right: 2rem;
        width: 350px;
        max-width: 90vw;
        background-color: white;
        border-radius: 0.75rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 1055;
        transition: transform 0.3s ease-out, opacity 0.3s, visibility 0.3s;
        transform: translateY(20px);
        opacity: 0;
        visibility: hidden;
    }

    .chatbox-widget.visible {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }

    .chatbox-header {
        background: #212529;
        color: white;
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chatbox-body {
        padding: 1rem;
        height: 400px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        /* Added gap for better spacing between messages */
    }

    /* Message Styling */
    .message-container {
        display: flex;
        flex-direction: column;
    }

    .message-sender {
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .message {
        max-width: 85%;
        padding: 0.5rem 1rem;
        border-radius: 1rem;
        word-wrap: break-word;
    }

    /* Sent Message Container */
    .message-container.sent {
        align-self: flex-end;
        align-items: flex-end;
    }

    .message.sent {
        background-color: #1a1d21;
        /* dark charcoal tone */
        color: white;
        border-bottom-right-radius: 0.25rem;
    }

    /* Received Message Container */
    .message-container.received {
        align-self: flex-start;
        align-items: flex-start;
    }

    .message.received {
        background-color: #e9ecef;
        color: #343a40;
        border-bottom-left-radius: 0.25rem;
    }

    .chatbox-footer {
        padding: 0.75rem;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }

    .chatbox-footer .form-control {
        border-radius: 2rem;
    }


    .message-time {
        font-size: 0.7rem;
        margin-top: 0.25rem;
        color: #f1f1f1;
        /* Very light gray */
        text-align: right;
        font-weight: 500;
    }

    .message-container.received .message-time {
        color: #6c757d;
        text-align: left;
    }
</style>

<!-- Floating Chat Button -->
<button class="btn btn-primary rounded-circle shadow-lg chat-fab" id="chatButton" type="button" title="Open Chat">
    <i class="bi bi-chat-dots-fill"></i>
</button>

<!-- Chatbox Widget -->
<div class="chatbox-widget" id="chatbox">
    <div class="chatbox-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2"></i>Team Sync</h6>
        <button class="btn-close btn-close-white" id="closeChatButton" type="button" title="Close Chat"></button>
    </div>
    <div class="chatbox-body" id="chatboxBody">

    </div>
    <div class="chatbox-footer">
        <form id="chatForm">
            <div class="input-group">
                <input type="text" id="chatInput" class="form-control" placeholder="Type a message..." autocomplete="off" required>
                <button class="btn btn-primary rounded-end-circle" type="submit" title="Send">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Firebase SDKs -->
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-database-compat.js"></script>

<script>
    // Inject PHP session name into JS
    const currentUser = "<?= htmlspecialchars($_SESSION['name']) ?>";
    const currentUserID = "<?= htmlspecialchars($_SESSION['user_id']) ?>";

    // Firebase configuration
    const firebaseConfig = {
        apiKey: "AIzaSyBSeJONRBgtV1ubtO9THKlDUv3rCqGgnRE",
        authDomain: "chatcarus.firebaseapp.com",
        databaseURL: "https://chatcarus-default-rtdb.asia-southeast1.firebasedatabase.app",
        projectId: "chatcarus",
        storageBucket: "chatcarus.firebasestorage.app",
        messagingSenderId: "631229423127",
        appId: "1:631229423127:web:efc3bc7ac058b9eb950652"
    };

    firebase.initializeApp(firebaseConfig);
    const database = firebase.database();
    const messagesRef = database.ref("messages");

    document.addEventListener('DOMContentLoaded', function() {
        const chatButton = document.getElementById('chatButton');
        const closeChatButton = document.getElementById('closeChatButton');
        const chatbox = document.getElementById('chatbox');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const chatboxBody = document.getElementById('chatboxBody');

        function toggleChatbox() {
            chatbox.classList.toggle('visible');
            chatButton.classList.toggle('hidden');
        }

        chatButton.addEventListener('click', toggleChatbox);
        closeChatButton.addEventListener('click', toggleChatbox);

        function addMessage(sender, text, type, timestamp) {
            const messageContainer = document.createElement('div');
            messageContainer.classList.add('message-container', type);

            const senderDiv = document.createElement('div');
            senderDiv.classList.add('message-sender');
            if (type === 'sent') senderDiv.classList.add('text-end');
            senderDiv.textContent = (type === 'sent') ? "You" : sender;

            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', type);
            messageDiv.textContent = text;

            // Time Formatting
            const timeDiv = document.createElement('div');
            timeDiv.classList.add('message-time');
            const time = new Date(timestamp || Date.now());
            timeDiv.textContent = time.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
            timeDiv.style.fontSize = "0.7rem";
            timeDiv.style.marginTop = "0.25rem";
            timeDiv.style.color = "#6c757d";
            if (type === 'sent') timeDiv.style.textAlign = "right";

            messageDiv.appendChild(timeDiv);
            messageContainer.appendChild(senderDiv);
            messageContainer.appendChild(messageDiv);
            chatboxBody.appendChild(messageContainer);

            chatboxBody.scrollTop = chatboxBody.scrollHeight;
        }


        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageText = chatInput.value.trim();

            if (messageText) {
                const newMessage = {
                    sender: currentUser,
                    user_id: currentUserID, // 👈 Add this line to store the user ID
                    text: messageText,
                    timestamp: Date.now()
                };
                messagesRef.push(newMessage);
                chatInput.value = '';
            }
        });


        // Cleanup messages older than 10 days
        const TEN_DAYS_MS = 10 * 24 * 60 * 60 * 1000;
        const now = Date.now();

        messagesRef.once("value", function(snapshot) {
            snapshot.forEach(function(childSnapshot) {
                const msg = childSnapshot.val();
                if (msg.timestamp && now - msg.timestamp > TEN_DAYS_MS) {
                    messagesRef.child(childSnapshot.key).remove();
                }
            });
        });

        messagesRef.on("child_added", function(snapshot) {
            const msg = snapshot.val();
            const type = msg.user_id === currentUserID ? "sent" : "received";
            addMessage(msg.sender, msg.text, type, msg.timestamp);
        });

    });
</script>