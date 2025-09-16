<?php
require_once 'config/config.php';

// Jeśli użytkownik jest już zalogowany, przekieruj do dashboardu
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Pobierz liczbę zarejestrowanych użytkowników i subdomen
try {
    $db = new DatabaseManager();
    $userCount = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1 ");
    $registeredUsers = $userCount ? $userCount['count'] : 0;
    
    $subdomainCount = $db->selectOne("SELECT COUNT(*) as count FROM subdomains WHERE status = 'active'");
    $activeSubdomains = $subdomainCount ? $subdomainCount['count'] : 0;
} catch (Exception $e) {
    $registeredUsers = 0;
    $activeSubdomains = 0;
    error_log("Error getting counts: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Rezerwacja Subdomen</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
        }
        
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover { 
            transition: all 0.3s ease;
        }
        
        .card-hover:hover { 
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .feature-icon { 
            transition: all 0.3s ease;
        }
        
        .feature-icon:hover { 
            transform: scale(1.1);
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
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .animate-slide-in-left {
            animation: slideInLeft 0.8s ease-out;
        }
        
        .animate-slide-in-right {
            animation: slideInRight 0.8s ease-out;
        }
        
        .animate-scale-in {
            animation: scaleIn 0.6s ease-out;
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .section-transition {
            position: relative;
            overflow: hidden;
        }
        
        .section-transition::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }
        
        .parallax-bg {
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Hover effects */
        .hover-lift:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
        }
        
        .hover-glow:hover {
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
            transition: box-shadow 0.3s ease;
        }
        
        /* Gradient text animation */
        .gradient-text {
            background: linear-gradient(-45deg, #667eea, #764ba2, #667eea, #764ba2);
            background-size: 400% 400%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i class="fas fa-globe text-2xl text-indigo-600 mr-3"></i>
                    <span class="text-xl font-bold text-gray-900"><?php echo SITE_NAME; ?></span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>Logowanie
                    </a>
                    <a href="register.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Rejestracja
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="bg-indigo-600 text-white py-24">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-6xl font-bold mb-6 animate-fade-in-up leading-tight">
                    Rezerwuj Subdomeny<br>
                    <span class="text-yellow-300">Szybko i Łatwo</span>
                </h1>
                <p class="text-xl mb-12 max-w-2xl mx-auto animate-fade-in-up opacity-90">
                    Profesjonalna platforma do zarządzania subdomenami z zaawansowanymi funkcjami bezpieczeństwa i intuicyjnym interfejsem.
                </p>
                
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12 max-w-2xl mx-auto">
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="text-4xl font-bold mb-2"><?php echo number_format($registeredUsers); ?></div>
                        <div class="text-sm opacity-80">Zarejestrowanych Użytkowników</div>
                    </div>
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="text-4xl font-bold mb-2"><?php echo number_format($activeSubdomains); ?></div>
                        <div class="text-sm opacity-80">Aktywnych Subdomen</div>
                    </div>
                    <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-6 border border-white border-opacity-20">
                        <div class="text-4xl font-bold mb-2">99.9%</div>
                        <div class="text-sm opacity-80">Gwarantowany Uptime</div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="register.php" class="bg-white text-purple-600 px-8 py-4 rounded-xl font-semibold hover:bg-gray-100 transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 hover-glow">
                        <i class="fas fa-rocket mr-2"></i>Rozpocznij Teraz
                    </a>
                    <a href="login.php" class="border-2 border-white text-white px-8 py-4 rounded-xl font-semibold hover:bg-white hover:text-purple-600 transition duration-300 hover-glow">
                        <i class="fas fa-sign-in-alt mr-2"></i>Zaloguj się
                    </a>
                </div>
            </div>
        </div>
    </section>
    <!-- Wave Transition -->
    <div class="h-24 bg-gradient-to-b from-gray-50 to-white relative overflow-hidden">
        <svg class="absolute bottom-0 w-full h-full" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="currentColor" class="text-blue-500"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="currentColor" class="text-purple-500"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="currentColor" class="text-indigo-600"></path>
        </svg>
    </div>


    <!-- Features Section -->
    <section id="features" class="py-20 bg-gradient-to-br from-gray-50 to-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold text-gray-800 mb-6 animate-fade-in-up">Dlaczego wybrać nas?</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto animate-fade-in-up leading-relaxed">
                    Oferujemy kompleksowe rozwiązania do zarządzania subdomenami z najwyższym poziomem bezpieczeństwa i niezawodnością.
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                 <div class="bg-white p-8 rounded-2xl shadow-xl card-hover animate-slide-in-left border border-gray-100 glass-effect hover-lift">
                     <div class="feature-icon text-purple-600 text-5xl mb-6 flex justify-center animate-float">
                         <i class="fas fa-shield-alt"></i>
                     </div>
                     <h3 class="text-2xl font-bold mb-4 text-center text-gray-800">Hosting Plików</h3>
                     <p class="text-gray-600 text-center leading-relaxed">
                         Hostuj swoje pliki HTML, CSS, JS bezpośrednio na naszych serwerach z automatycznym SSL i CDN.
                     </p>
                     <div class="mt-6 flex justify-center">
                         <span class="bg-purple-100 text-purple-600 px-4 py-2 rounded-full text-sm font-semibold animate-scale-in">
                             SSL + CDN
                         </span>
                     </div>
                 </div>
                 
                 <div class="bg-white p-8 rounded-2xl shadow-xl card-hover animate-fade-in-up border border-gray-100 glass-effect hover-lift">
                     <div class="feature-icon text-blue-600 text-5xl mb-6 flex justify-center animate-float" style="animation-delay: 0.5s;">
                         <i class="fas fa-exchange-alt"></i>
                     </div>
                     <h3 class="text-2xl font-bold mb-4 text-center text-gray-800">Przekierowania IP</h3>
                     <p class="text-gray-600 text-center leading-relaxed">
                         Skonfiguruj przekierowania na własne serwery z pełną kontrolą nad ruchem i load balancing.
                     </p>
                     <div class="mt-6 flex justify-center">
                         <span class="bg-blue-100 text-blue-600 px-4 py-2 rounded-full text-sm font-semibold animate-scale-in" style="animation-delay: 0.3s;">
                             Load Balancing
                         </span>
                     </div>
                 </div>
                 
                 <div class="bg-white p-8 rounded-2xl shadow-xl card-hover animate-slide-in-right border border-gray-100 glass-effect hover-lift">
                     <div class="feature-icon text-green-600 text-5xl mb-6 flex justify-center animate-float" style="animation-delay: 1s;">
                         <i class="fas fa-chart-line"></i>
                     </div>
                     <h3 class="text-2xl font-bold mb-4 text-center text-gray-800">Analityka</h3>
                     <p class="text-gray-600 text-center leading-relaxed">
                         Monitoruj ruch, wydajność i dostępność swoich subdomen w czasie rzeczywistym z zaawansowanymi raportami.
                     </p>
                     <div class="mt-6 flex justify-center">
                         <span class="bg-green-100 text-green-600 px-4 py-2 rounded-full text-sm font-semibold animate-scale-in" style="animation-delay: 0.6s;">
                             Real-time
                         </span>
                     </div>
                 </div>
             </div>
        </div>
    </section>

    <!-- Wave Transition -->
    <div class="h-24 bg-gradient-to-b from-gray-50 to-white relative overflow-hidden">
        <svg class="absolute bottom-0 w-full h-full" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="currentColor" class="text-blue-500"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="currentColor" class="text-purple-500"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="currentColor" class="text-white"></path>
        </svg>
    </div>

    <!-- How it works Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold text-gray-800 mb-6 animate-fade-in-up">Jak to działa?</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto animate-fade-in-up leading-relaxed">
                    Prosty proces w trzech krokach do uruchomienia Twojej subdomeny. Bez skomplikowanych konfiguracji.
                </p>
            </div>
            
            <div class="max-w-5xl mx-auto">
                <div class="grid md:grid-cols-3 gap-12 relative">
                    <!-- Connection lines for desktop -->
                    <div class="hidden md:block absolute top-20 left-1/3 w-1/3 h-0.5 bg-gradient-to-r from-purple-300 to-blue-300 transform -translate-y-1/2"></div>
                    <div class="hidden md:block absolute top-20 right-1/3 w-1/3 h-0.5 bg-gradient-to-r from-blue-300 to-green-300 transform -translate-y-1/2"></div>
                    
                    <div class="text-center animate-fade-in-up relative">
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <span class="text-3xl font-bold text-white">1</span>
                        </div>
                        <h3 class="text-2xl font-bold mb-4 text-gray-800">Zarejestruj się</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Utwórz darmowe konto i zweryfikuj swój adres email w kilka sekund. Proces rejestracji jest całkowicie bezpłatny.
                        </p>
                        <div class="mt-4">
                            <i class="fas fa-user-plus text-purple-500 text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="text-center animate-fade-in-up relative">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <span class="text-3xl font-bold text-white">2</span>
                        </div>
                        <h3 class="text-2xl font-bold mb-4 text-gray-800">Skonfiguruj subdomenę</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Wybierz unikalną nazwę subdomeny i zdecyduj czy chcesz hostować pliki czy przekierować na własny serwer.
                        </p>
                        <div class="mt-4">
                            <i class="fas fa-cogs text-blue-500 text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="text-center animate-fade-in-up relative">
                        <div class="bg-gradient-to-br from-green-500 to-green-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                            <span class="text-3xl font-bold text-white">3</span>
                        </div>
                        <h3 class="text-2xl font-bold mb-4 text-gray-800">Gotowe!</h3>
                        <p class="text-gray-600 leading-relaxed">
                            Twoja subdomena jest aktywna i gotowa do użycia w ciągu kilku minut. Automatyczne SSL i CDN włączone.
                        </p>
                        <div class="mt-4">
                            <i class="fas fa-rocket text-green-500 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-gradient-to-br from-purple-600 via-purple-700 to-indigo-800 text-white relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 bg-black bg-opacity-20"></div>
        <div class="absolute top-0 left-0 w-full h-full">
            <div class="absolute top-10 left-10 w-20 h-20 bg-white bg-opacity-10 rounded-full"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-white bg-opacity-5 rounded-full"></div>
            <div class="absolute bottom-20 left-1/4 w-12 h-12 bg-white bg-opacity-10 rounded-full"></div>
            <div class="absolute bottom-10 right-10 w-24 h-24 bg-white bg-opacity-5 rounded-full"></div>
        </div>
        
        <div class="container mx-auto px-6 text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-6xl font-bold mb-6 animate-fade-in-up leading-tight">
                    Gotowy na start?
                </h2>
                <p class="text-2xl mb-12 max-w-3xl mx-auto animate-fade-in-up opacity-90 leading-relaxed">
                    Dołącz do tysięcy użytkowników, którzy już korzystają z naszej platformy do zarządzania subdomenami. Rozpocznij swoją przygodę już dziś!
                </p>
                
                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <a href="register.php" class="bg-white text-purple-600 px-10 py-4 rounded-xl font-bold text-lg hover:bg-gray-100 transition duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-2 flex items-center hover-glow">
                        <i class="fas fa-rocket mr-3"></i>
                        Rozpocznij za darmo
                        <i class="fas fa-arrow-right ml-3"></i>
                    </a>
                    <a href="#features" class="border-2 border-white text-white px-10 py-4 rounded-xl font-bold text-lg hover:bg-white hover:text-purple-600 transition duration-300 flex items-center hover-glow">
                        <i class="fas fa-info-circle mr-3"></i>
                        Dowiedz się więcej
                    </a>
                </div>
                
                <div class="mt-12 flex justify-center items-center space-x-8 text-sm opacity-80">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-400"></i>
                        Darmowa rejestracja
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-400"></i>
                        Bez ukrytych kosztów
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-400"></i>
                        Wsparcie 24/7
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
        <div class="container mx-auto px-6 py-16">
            <div class="grid md:grid-cols-4 gap-12">
                <div class="md:col-span-2">
                    <div class="flex items-center mb-6">
                        <div class="bg-gradient-to-r from-purple-500 to-blue-500 w-10 h-10 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-globe text-white text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h3>
                    </div>
                    <p class="text-gray-400 text-lg leading-relaxed mb-6 max-w-md">
                        Profesjonalna platforma do zarządzania subdomenami z zaawansowanymi funkcjami bezpieczeństwa i intuicyjnym interfejsem.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="bg-gray-800 hover:bg-purple-600 w-10 h-10 rounded-full flex items-center justify-center transition duration-300">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="bg-gray-800 hover:bg-purple-600 w-10 h-10 rounded-full flex items-center justify-center transition duration-300">
                            <i class="fab fa-github"></i>
                        </a>
                        <a href="#" class="bg-gray-800 hover:bg-purple-600 w-10 h-10 rounded-full flex items-center justify-center transition duration-300">
                            <i class="fab fa-discord"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-xl font-bold mb-6 text-white">Funkcje</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-server mr-2 w-4"></i>Hosting plików</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-exchange-alt mr-2 w-4"></i>Przekierowania IP</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-chart-line mr-2 w-4"></i>Analityka</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-code mr-2 w-4"></i>API</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-xl font-bold mb-6 text-white">Wsparcie</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-book mr-2 w-4"></i>Dokumentacja</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-question-circle mr-2 w-4"></i>FAQ</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-envelope mr-2 w-4"></i>Kontakt</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300 flex items-center"><i class="fas fa-heartbeat mr-2 w-4"></i>Status serwisu</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-gray-400 mb-4 md:mb-0">
                        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Wszystkie prawa zastrzeżone.</p>
                    </div>
                    <div class="flex space-x-6 text-sm">
                        <a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300">Regulamin</a>
                        <a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300">Polityka prywatności</a>
                        <a href="#" class="text-gray-400 hover:text-purple-400 transition duration-300">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.card-hover').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>