<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat de Cadastro - D&Z Revendedores</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff00d4 0%, #111e88 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .chat-container {
            background: white;
            border-radius: 20px;
            max-width: 400px;
            width: 100%;
            height: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #ff00d4, #e91e63);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
        }
        
        .chat-header h1 {
            font-size: 1.4rem;
            margin-bottom: 0.2rem;
        }
        
        .chat-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .chat-messages {
            flex: 1;
            padding: 2rem 1.5rem 1rem;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 1rem;
            opacity: 0;
            transform: translateY(20px);
            animation: slideIn 0.4s ease forwards;
        }
        
        .message.bot {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }
        
        .bot-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #ff00d4, #e91e63);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .message-content {
            background: white;
            padding: 0.8rem 1rem;
            border-radius: 18px 18px 18px 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 80%;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .message.user {
            display: flex;
            justify-content: flex-end;
        }
        
        .message.user .message-content {
            background: linear-gradient(135deg, #ff00d4, #e91e63);
            color: white;
            border-radius: 18px 18px 5px 18px;
            max-width: 75%;
        }
        
        .typing-indicator {
            display: none;
            margin-bottom: 1rem;
        }
        
        .typing-indicator.active {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }
        
        .typing-dots {
            background: white;
            padding: 0.8rem 1rem;
            border-radius: 18px 18px 18px 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .dots {
            display: flex;
            gap: 4px;
        }
        
        .dot {
            width: 6px;
            height: 6px;
            background: #ccc;
            border-radius: 50%;
            animation: bounce 1.5s ease-in-out infinite;
        }
        
        .dot:nth-child(2) { animation-delay: 0.3s; }
        .dot:nth-child(3) { animation-delay: 0.6s; }
        
        .chat-input {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eee;
            background: white;
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .chat-input input,
        .chat-input select {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 2px solid #eee;
            border-radius: 20px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .chat-input input:focus,
        .chat-input select:focus {
            border-color: #ff00d4;
        }
        
        .send-btn {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #ff00d4, #e91e63);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            font-size: 1.1rem;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
        }
        
        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .options-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .option-btn {
            background: white;
            border: 2px solid #ff00d4;
            color: #ff00d4;
            padding: 0.6rem 1rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
            text-align: left;
        }
        
        .option-btn:hover {
            background: #ff00d4;
            color: white;
        }
        
        .progress-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255,255,255,0.3);
        }
        
        .progress-bar {
            height: 100%;
            background: white;
            transition: width 0.5s ease;
            width: 0%;
        }
        
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-8px); }
            60% { transform: translateY(-4px); }
        }
        
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Header do Chat -->
        <div class="chat-header">
            <h1>Assistente D&Z</h1>
            <p>Vamos te ajudar a se tornar um revendedor!</p>
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
        </div>

        <!-- Área das Mensagens -->
        <div class="chat-messages" id="chat-messages">
            <div class="message bot">
                <div class="bot-avatar">D&Z</div>
                <div class="message-content">
                    Olá! 👋 Sou o assistente da D&Z e estou aqui para te ajudar a se tornar nosso revendedor!
                </div>
            </div>
        </div>

        <!-- Indicador de digitação -->
        <div class="typing-indicator" id="typing-indicator">
            <div class="bot-avatar">D&Z</div>
            <div class="typing-dots">
                <div class="dots">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
            </div>
        </div>

        <!-- Área de Input -->
        <div class="chat-input">
            <form id="chat-form">
                <div class="input-group">
                    <input type="text" id="user-input" placeholder="Digite sua resposta..." disabled>
                    <select id="user-select" class="hidden">
                        <option value="">Selecione uma opção</option>
                    </select>
                    <button type="submit" class="send-btn" id="send-btn" disabled>
                        ➤
                    </button>
                </div>
                <div id="options-container" class="options-container hidden"></div>
            </form>
        </div>
    </div>

    <script>
        let currentStep = 0;
        let userData = {};
        let isProcessing = false;

        const steps = [
            {
                question: "Primeiro, qual é o seu nome completo? 😊",
                type: "text",
                field: "nome",
                validation: (value) => value.length >= 2 ? null : "Por favor, digite seu nome completo"
            },
            {
                question: "Perfeito! Agora me diga seu WhatsApp (com DDD) 📱",
                type: "text",
                field: "whatsapp",
                placeholder: "Ex: 11999999999",
                validation: (value) => /^\d{10,11}$/.test(value) ? null : "Digite apenas números (DDD + telefone)"
            },
            {
                question: "Ótimo! Qual é o nome da sua loja? 🏪",
                type: "text",
                field: "loja",
                validation: (value) => value.length >= 2 ? null : "Por favor, digite o nome da loja"
            },
            {
                question: "Em qual cidade fica sua loja? 🌎",
                type: "text",
                field: "cidade",
                validation: (value) => value.length >= 2 ? null : "Por favor, digite a cidade"
            },
            {
                question: "E o estado? 📍",
                type: "select",
                field: "estado",
                options: [
                    { value: "AC", text: "Acre" },
                    { value: "AL", text: "Alagoas" },
                    { value: "AP", text: "Amapá" },
                    { value: "AM", text: "Amazonas" },
                    { value: "BA", text: "Bahia" },
                    { value: "CE", text: "Ceará" },
                    { value: "DF", text: "Distrito Federal" },
                    { value: "ES", text: "Espírito Santo" },
                    { value: "GO", text: "Goiás" },
                    { value: "MA", text: "Maranhão" },
                    { value: "MT", text: "Mato Grosso" },
                    { value: "MS", text: "Mato Grosso do Sul" },
                    { value: "MG", text: "Minas Gerais" },
                    { value: "PA", text: "Pará" },
                    { value: "PB", text: "Paraíba" },
                    { value: "PR", text: "Paraná" },
                    { value: "PE", text: "Pernambuco" },
                    { value: "PI", text: "Piauí" },
                    { value: "RJ", text: "Rio de Janeiro" },
                    { value: "RN", text: "Rio Grande do Norte" },
                    { value: "RS", text: "Rio Grande do Sul" },
                    { value: "RO", text: "Rondônia" },
                    { value: "RR", text: "Roraima" },
                    { value: "SC", text: "Santa Catarina" },
                    { value: "SP", text: "São Paulo" },
                    { value: "SE", text: "Sergipe" },
                    { value: "TO", text: "Tocantins" }
                ]
            },
            {
                question: "Qual é o tipo do seu estabelecimento? 🏢",
                type: "options",
                field: "ramo",
                options: [
                    { value: "salao_beleza", text: "Salão de Beleza" },
                    { value: "clinica_estetica", text: "Clínica Estética" },
                    { value: "loja_cosmeticos", text: "Loja de Cosméticos" },
                    { value: "studio_unhas", text: "Studio de Unhas" },
                    { value: "outro", text: "Outro" }
                ]
            },
            {
                question: "Qual é o faturamento médio mensal da sua loja? 💰",
                type: "options",
                field: "faturamento",
                options: [
                    { value: "ate_5000", text: "Até R$ 5.000" },
                    { value: "5001_15000", text: "R$ 5.001 a R$ 15.000" },
                    { value: "15001_30000", text: "R$ 15.001 a R$ 30.000" },
                    { value: "acima_30000", text: "Acima de R$ 30.000" }
                ]
            },
            {
                question: "Por último, qual é o seu principal interesse? ✨",
                type: "options",
                field: "interesse",
                options: [
                    { value: "unha", text: "Material de Unha 💅" },
                    { value: "cilios", text: "Material de Cílios 👁️" },
                    { value: "unha,cilios", text: "Ambos (Unha e Cílios) 💅👁️" }
                ]
            }
        ];

        function addMessage(content, isBot = true, delay = 0) {
            setTimeout(() => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${isBot ? 'bot' : 'user'}`;
                
                if (isBot) {
                    messageDiv.innerHTML = `
                        <div class="bot-avatar">D&Z</div>
                        <div class="message-content">${content}</div>
                    `;
                } else {
                    messageDiv.innerHTML = `
                        <div class="message-content">${content}</div>
                    `;
                }
                
                document.getElementById('chat-messages').appendChild(messageDiv);
                scrollToBottom();
            }, delay);
        }

        function showTyping() {
            document.getElementById('typing-indicator').classList.add('active');
            scrollToBottom();
        }

        function hideTyping() {
            document.getElementById('typing-indicator').classList.remove('active');
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function updateProgress() {
            const progress = (currentStep / steps.length) * 100;
            document.querySelector('.progress-bar').style.width = progress + '%';
        }

        function setupInput(step) {
            const input = document.getElementById('user-input');
            const select = document.getElementById('user-select');
            const optionsContainer = document.getElementById('options-container');
            const sendBtn = document.getElementById('send-btn');

            // Limpar estado anterior
            input.classList.remove('hidden');
            select.classList.add('hidden');
            optionsContainer.classList.add('hidden');
            optionsContainer.innerHTML = '';

            if (step.type === 'text') {
                input.placeholder = step.placeholder || "Digite sua resposta...";
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();
            } else if (step.type === 'select') {
                input.classList.add('hidden');
                select.classList.remove('hidden');
                select.innerHTML = '<option value="">Selecione uma opção</option>';
                
                step.options.forEach(option => {
                    const optionEl = document.createElement('option');
                    optionEl.value = option.value;
                    optionEl.textContent = option.text;
                    select.appendChild(optionEl);
                });
                
                select.disabled = false;
                sendBtn.disabled = false;
                select.focus();
            } else if (step.type === 'options') {
                input.classList.add('hidden');
                optionsContainer.classList.remove('hidden');
                
                step.options.forEach(option => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'option-btn';
                    btn.textContent = option.text;
                    btn.onclick = () => selectOption(option.value, option.text);
                    optionsContainer.appendChild(btn);
                });
                
                sendBtn.disabled = true;
            }
        }

        function selectOption(value, text) {
            addMessage(text, false);
            processAnswer(value);
        }

        function processAnswer(answer) {
            if (isProcessing) return;
            isProcessing = true;

            const step = steps[currentStep];
            
            // Validar resposta
            if (step.validation && step.type === 'text') {
                const error = step.validation(answer);
                if (error) {
                    showTyping();
                    setTimeout(() => {
                        hideTyping();
                        addMessage(`❌ ${error}. Tente novamente:`);
                        setupInput(step);
                        isProcessing = false;
                    }, 1000);
                    return;
                }
            }

            // Salvar resposta
            userData[step.field] = answer;
            
            // Resposta do bot
            const responses = [
                "Perfeito! 👍",
                "Ótimo! ✨",
                "Excelente! 🎉",
                "Muito bem! 🌟",
                "Entendi! ✅"
            ];
            
            showTyping();
            
            setTimeout(() => {
                hideTyping();
                addMessage(responses[Math.floor(Math.random() * responses.length)]);
                
                currentStep++;
                updateProgress();
                
                if (currentStep < steps.length) {
                    setTimeout(() => {
                        addMessage(steps[currentStep].question);
                        setupInput(steps[currentStep]);
                        isProcessing = false;
                    }, 1000);
                } else {
                    finalizeCadastro();
                }
            }, 1500);
        }

        function finalizeCadastro() {
            showTyping();
            
            setTimeout(() => {
                hideTyping();
                addMessage("🎉 Parabéns! Seu cadastro foi realizado com sucesso!");
                
                setTimeout(() => {
                    addMessage("Agora vou te conectar com uma de nossas consultoras especialistas! 👩‍💼");
                    
                    // Enviar dados para o servidor
                    fetch('processar-cadastro.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(userData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            setTimeout(() => {
                                const whatsappMsg = `Olá! Acabei de me cadastrar como revendedor D&Z. Meu nome é ${userData.nome}.`;
                                const whatsappUrl = `https://wa.me/55${data.vendedora_whatsapp}?text=${encodeURIComponent(whatsappMsg)}`;
                                
                                addMessage(`🎯 Você foi direcionado(a) para: <strong>${data.vendedora_nome}</strong>`);
                                
                                setTimeout(() => {
                                    addMessage(`<a href="${whatsappUrl}" target="_blank" style="
                                        display: inline-block;
                                        background: linear-gradient(135deg, #25D366, #128C7E);
                                        color: white;
                                        text-decoration: none;
                                        padding: 12px 20px;
                                        border-radius: 20px;
                                        font-weight: bold;
                                        margin-top: 10px;
                                    ">💬 Conversar no WhatsApp</a>`);
                                    
                                    document.getElementById('user-input').disabled = true;
                                    document.getElementById('send-btn').disabled = true;
                                }, 1000);
                            }, 1500);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        addMessage("❌ Ops! Houve um erro. Tente novamente mais tarde.");
                    });
                }, 1500);
            }, 2000);
        }

        // Event listeners
        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = document.getElementById('user-input');
            const select = document.getElementById('user-select');
            
            let value = '';
            let displayText = '';
            
            if (!input.classList.contains('hidden') && input.value.trim()) {
                value = input.value.trim();
                displayText = value;
                input.value = '';
            } else if (!select.classList.contains('hidden') && select.value) {
                value = select.value;
                displayText = select.options[select.selectedIndex].text;
            }
            
            if (value) {
                addMessage(displayText, false);
                processAnswer(value);
            }
        });

        // Inicializar chat
        setTimeout(() => {
            addMessage(steps[0].question);
            setupInput(steps[0]);
        }, 1000);
    </script>
</body>
</html>