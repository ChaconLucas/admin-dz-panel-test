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
        
        .error {
            color: #e74c3c;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .success {
            text-align: center;
            color: #27ae60;
        }
        
        .success h2 {
            color: #27ae60;
            margin-bottom: 1rem;
        }
        
        #whatsapp-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        
        #vendedora-info {
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>D&Z</h1>
            <p>Pré-Cadastro de Revendedores</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 16.66%"></div>
        </div>
        
        <form id="cadastroForm">
            <!-- Etapa 1: Nome -->
            <div class="step active" data-step="1">
                <h2>Qual é o seu nome?</h2>
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" required>
                    <div class="error" id="error-nome"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(1)">Continuar</button>
            </div>
            
            <!-- Etapa 2: WhatsApp -->
            <div class="step" data-step="2">
                <h2>Qual é o seu WhatsApp?</h2>
                <div class="form-group">
                    <label for="whatsapp">WhatsApp (com DDD)</label>
                    <input type="tel" id="whatsapp" name="whatsapp" placeholder="11999999999" required>
                    <div class="error" id="error-whatsapp"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(2)">Continuar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(2)">Voltar</button>
            </div>
            
            <!-- Etapa 3: Nome da Loja -->
            <div class="step" data-step="3">
                <h2>Qual é o nome da sua loja?</h2>
                <div class="form-group">
                    <label for="loja">Nome da loja</label>
                    <input type="text" id="loja" name="loja" required>
                    <div class="error" id="error-loja"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(3)">Continuar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(3)">Voltar</button>
            </div>
            
            <!-- Etapa 4: Localização -->
            <div class="step" data-step="4">
                <h2>Onde fica sua loja?</h2>
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" required>
                    <div class="error" id="error-cidade"></div>
                </div>
                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" required>
                        <option value="">Selecione</option>
                        <option value="AC">Acre</option>
                        <option value="AL">Alagoas</option>
                        <option value="AP">Amapá</option>
                        <option value="AM">Amazonas</option>
                        <option value="BA">Bahia</option>
                        <option value="CE">Ceará</option>
                        <option value="DF">Distrito Federal</option>
                        <option value="ES">Espírito Santo</option>
                        <option value="GO">Goiás</option>
                        <option value="MA">Maranhão</option>
                        <option value="MT">Mato Grosso</option>
                        <option value="MS">Mato Grosso do Sul</option>
                        <option value="MG">Minas Gerais</option>
                        <option value="PA">Pará</option>
                        <option value="PB">Paraíba</option>
                        <option value="PR">Paraná</option>
                        <option value="PE">Pernambuco</option>
                        <option value="PI">Piauí</option>
                        <option value="RJ">Rio de Janeiro</option>
                        <option value="RN">Rio Grande do Norte</option>
                        <option value="RS">Rio Grande do Sul</option>
                        <option value="RO">Rondônia</option>
                        <option value="RR">Roraima</option>
                        <option value="SC">Santa Catarina</option>
                        <option value="SP">São Paulo</option>
                        <option value="SE">Sergipe</option>
                        <option value="TO">Tocantins</option>
                    </select>
                    <div class="error" id="error-estado"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(4)">Continuar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(4)">Voltar</button>
            </div>
            
            <!-- Etapa 5: Ramo -->
            <div class="step" data-step="5">
                <h2>Qual é o ramo da sua loja?</h2>
                <div class="form-group">
                    <label for="ramo">Tipo de estabelecimento</label>
                    <select id="ramo" name="ramo" required>
                        <option value="">Selecione</option>
                        <option value="esmalteria">Esmalteria</option>
                        <option value="salao">Salão de Beleza</option>
                        <option value="studio_cilios">Studio de Cílios</option>
                        <option value="loja_cosmeticos">Loja de Cosméticos</option>
                        <option value="perfumaria">Perfumaria</option>
                        <option value="farmacia">Farmácia</option>
                        <option value="outro">Outro</option>
                    </select>
                    <div class="error" id="error-ramo"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(5)">Continuar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(5)">Voltar</button>
            </div>
            
            <!-- Etapa 6: Faturamento -->
            <div class="step" data-step="6">
                <h2>Qual é o faturamento médio mensal?</h2>
                <div class="form-group">
                    <label for="faturamento">Faturamento mensal</label>
                    <select id="faturamento" name="faturamento" required>
                        <option value="">Selecione</option>
                        <option value="ate_5000">Até R$ 5.000</option>
                        <option value="5001_15000">R$ 5.001 a R$ 15.000</option>
                        <option value="15001_30000">R$ 15.001 a R$ 30.000</option>
                        <option value="acima_30000">Acima de R$ 30.000</option>
                    </select>
                    <div class="error" id="error-faturamento"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(6)">Continuar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(6)">Voltar</button>
            </div>
            
            <!-- Etapa 7: Interesse -->
            <div class="step" data-step="7">
                <h2>Qual é o seu interesse principal?</h2>
                <div class="form-group">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="interesse_unha" name="interesse[]" value="unha">
                            <label for="interesse_unha">Material de Unha</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="interesse_cilios" name="interesse[]" value="cilios">
                            <label for="interesse_cilios">Material de Cílios</label>
                        </div>
                    </div>
                    <div class="error" id="error-interesse"></div>
                </div>
                <button type="button" class="btn" onclick="nextStep(7)">Finalizar</button>
                <button type="button" class="btn btn-secondary" onclick="prevStep(7)">Voltar</button>
            </div>
            
            <!-- Sucesso -->
            <div class="step" data-step="8">
                <div class="success">
                    <h2>✅ Cadastro realizado com sucesso!</h2>
                    <p>Você foi direcionado(a) para uma de nossas consultoras:</p>
                    <div id="vendedora-info" style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, rgba(255,0,212,0.1) 0%, rgba(65,241,182,0.1) 100%); border-radius: 10px; border: 2px solid #ff00d4;">
                        <h3 id="vendedora-nome" style="color: #ff00d4; margin-bottom: 10px;"></h3>
                        <a id="whatsapp-link" href="#" target="_blank" style="
                            display: inline-block;
                            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
                            color: white;
                            text-decoration: none;
                            padding: 15px 25px;
                            border-radius: 25px;
                            font-weight: bold;
                            font-size: 16px;
                            transition: all 0.3s;
                            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
                        ">
                            💬 Falar no WhatsApp
                        </a>
                    </div>
                    <p><strong>Clique no link acima para conversar diretamente!</strong></p>
                </div>
            </div>
        </form>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 7;

        function updateProgress() {
            const progress = (currentStep / totalSteps) * 100;
            document.querySelector('.progress-bar').style.width = progress + '%';
        }

        function showStep(step) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.querySelector(`[data-step="${step}"]`).classList.add('active');
            updateProgress();
        }

        function validateStep(step) {
            clearErrors();
            let isValid = true;

            switch(step) {
                case 1:
                    const nome = document.getElementById('nome').value.trim();
                    if (!nome) {
                        showError('error-nome', 'Por favor, informe seu nome');
                        isValid = false;
                    } else if (nome.length < 2) {
                        showError('error-nome', 'Nome deve ter pelo menos 2 caracteres');
                        isValid = false;
                    }
                    break;

                case 2:
                    const whatsapp = document.getElementById('whatsapp').value.trim();
                    const whatsappRegex = /^\d{10,11}$/;
                    if (!whatsapp) {
                        showError('error-whatsapp', 'Por favor, informe seu WhatsApp');
                        isValid = false;
                    } else if (!whatsappRegex.test(whatsapp)) {
                        showError('error-whatsapp', 'WhatsApp deve conter apenas números (DDD + número)');
                        isValid = false;
                    }
                    break;

                case 3:
                    const loja = document.getElementById('loja').value.trim();
                    if (!loja) {
                        showError('error-loja', 'Por favor, informe o nome da loja');
                        isValid = false;
                    }
                    break;

                case 4:
                    const cidade = document.getElementById('cidade').value.trim();
                    const estado = document.getElementById('estado').value;
                    if (!cidade) {
                        showError('error-cidade', 'Por favor, informe a cidade');
                        isValid = false;
                    }
                    if (!estado) {
                        showError('error-estado', 'Por favor, selecione o estado');
                        isValid = false;
                    }
                    break;

                case 5:
                    const ramo = document.getElementById('ramo').value;
                    if (!ramo) {
                        showError('error-ramo', 'Por favor, selecione o ramo da loja');
                        isValid = false;
                    }
                    break;

                case 6:
                    const faturamento = document.getElementById('faturamento').value;
                    if (!faturamento) {
                        showError('error-faturamento', 'Por favor, selecione o faturamento');
                        isValid = false;
                    }
                    break;

                case 7:
                    const interesseChecked = document.querySelectorAll('input[name="interesse[]"]:checked');
                    if (interesseChecked.length === 0) {
                        showError('error-interesse', 'Por favor, selecione pelo menos um interesse');
                        isValid = false;
                    }
                    break;
            }

            return isValid;
        }

        function showError(elementId, message) {
            document.getElementById(elementId).textContent = message;
        }

        function clearErrors() {
            document.querySelectorAll('.error').forEach(error => error.textContent = '');
        }

        function nextStep(step) {
            if (validateStep(step)) {
                if (step === 7) {
                    submitForm();
                } else {
                    currentStep = step + 1;
                    showStep(currentStep);
                }
            }
        }

        function prevStep(step) {
            currentStep = step - 1;
            showStep(currentStep);
        }

        function submitForm() {
            const formData = new FormData();
            
            formData.append('nome', document.getElementById('nome').value);
            formData.append('whatsapp', document.getElementById('whatsapp').value);
            formData.append('loja', document.getElementById('loja').value);
            formData.append('cidade', document.getElementById('cidade').value);
            formData.append('estado', document.getElementById('estado').value);
            formData.append('ramo', document.getElementById('ramo').value);
            formData.append('faturamento', document.getElementById('faturamento').value);
            
            const interesseCheckboxes = document.querySelectorAll('input[name="interesse[]"]:checked');
            interesseCheckboxes.forEach(checkbox => {
                formData.append('interesse[]', checkbox.value);
            });

            fetch('processar-cadastro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    // Atualizar informações da vendedora na tela de sucesso
                    if (data.vendedora) {
                        document.getElementById('vendedora-nome').textContent = data.vendedora.nome;
                        document.getElementById('whatsapp-link').href = data.vendedora.whatsapp_link;
                    }
                    
                    currentStep = 8;
                    showStep(currentStep);
                } else {
                    alert('Erro ao cadastrar: ' + data.message);
                    if (data.debug) {
                        console.error('Debug info:', data.debug);
                    }
                    if (data.trace) {
                        console.error('Stack trace:', data.trace);
                    }
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao enviar cadastro: ' + error.message);
            });
        }
    </script>
</body>
</html>