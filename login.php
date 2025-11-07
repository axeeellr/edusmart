<?php 
require_once 'includes/config.php'; 

if(isset($_SESSION['user_id'])) { 
    redirigirSegunRol(); 
} 
 
if($_SERVER['REQUEST_METHOD'] == 'POST') { 
    $username = trim($_POST['username']); 
    $password = trim($_POST['password']); 
     
    if(verificarLogin($username, $password)) { 
        redirigirSegunRol(); 
    } else { 
        $error = "Usuario o contraseña incorrectos"; 
    } 
} 
?> 
 
<!DOCTYPE html> 
<html lang="es"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Login - <?php echo APP_NAME; ?></title> 
    <script src="https://cdn.tailwindcss.com"></script> 
    <style> 
        body { 
            background-image: url('Imagenes/70233.jpg'); 
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed; 
            background-repeat: no-repeat; 
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .shake {
            animation: shake 0.4s ease-in-out;
        }
        
        .input-icon {
            transition: all 0.3s ease;
        }
        
        input:focus + .input-icon {
            color: #3b82f6;
        }
        
        .btn-submit {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit.loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .password-toggle {
            cursor: pointer;
            user-select: none;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #3b82f6;
        }
    </style> 
</head> 
<body class="min-h-screen"> 
    <div class="min-h-screen flex items-center justify-center bg-black bg-opacity-40"> 
        <div class="bg-white/95 backdrop-blur-md p-8 rounded-2xl shadow-2xl w-full max-w-md fade-in <?php echo isset($error) ? 'shake' : ''; ?>"> 
            <!-- Logo/Icono -->
            <div class="flex justify-center mb-6">
                <div class="bg-gradient-to-br from-blue-500 to-purple-600 p-4 rounded-full shadow-lg">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
            </div>
            
            <h1 class="text-3xl font-bold text-center mb-2 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                <?php echo APP_NAME; ?>
            </h1>
            <p class="text-center text-gray-600 mb-6">Sistema de Gestión Escolar</p>
             
            <?php if(isset($error)): ?> 
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo $error; ?></span>
                </div> 
            <?php endif; ?> 
             
            <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST"> 
                <!-- Campo Usuario -->
                <div class="mb-5 relative"> 
                    <label for="username" class="block text-gray-700 font-semibold mb-2">Usuario</label> 
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-gray-400 input-icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                            placeholder="Ingresa tu usuario"
                            autocomplete="off" 
                            required
                        > 
                    </div>
                </div> 
                 
                <!-- Campo Contraseña -->
                <div class="mb-6 relative"> 
                    <label for="password" class="block text-gray-700 font-semibold mb-2">Contraseña</label> 
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-gray-400 input-icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                            placeholder="Ingresa tu contraseña"
                            required
                        >
                        <span class="absolute right-3 top-3 text-gray-400 password-toggle" onclick="togglePassword()">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </span>
                    </div>
                </div>
                

                 
                <!-- Botón Submit -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="btn-submit w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-xl font-semibold shadow-lg hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-blue-300"
                > 
                    <span id="btnText">Iniciar Sesión</span>
                </button> 
            </form>
            
            <!-- Footer -->
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Conexión segura :D
                </p>
            </div>
        </div> 
    </div>
    
    <script>
        // Toggle mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
        
        // Loading state en el botón al enviar
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            
            btn.classList.add('loading');
            btn.disabled = true;
            btnText.style.opacity = '0';
        });
        
        // Guardar/cargar usuario recordado
        window.addEventListener('DOMContentLoaded', function() {
            const savedUsername = localStorage.getItem('rememberedUsername');
            const rememberCheckbox = document.querySelector('input[name="remember"]');
            if (savedUsername && rememberCheckbox) {
                document.getElementById('username').value = savedUsername;
                rememberCheckbox.checked = true;
            }
        });
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const rememberCheckbox = document.querySelector('input[name="remember"]');
            if (rememberCheckbox) {
                const remember = rememberCheckbox.checked;
                const username = document.getElementById('username').value;
                
                if (remember) {
                    localStorage.setItem('rememberedUsername', username);
                } else {
                    localStorage.removeItem('rememberedUsername');
                }
            }
        });
    </script>
</body> 
</html>