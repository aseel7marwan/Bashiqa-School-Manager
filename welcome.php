<?php
/**
 * الصفحة الترحيبية - Welcome Page
 * تصميم عصري واحترافي - مدرسة ابتدائية للبنين
 * 
 * @package SchoolManager
 * @access  عام
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/translations.php';

$theme = getUserTheme();
$isLoggedIn = isLoggedIn();
$baseUrl = getBaseUrl();
$currentLang = getLang();
$direction = getDirection();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= __('مدرسة بعشيقة الابتدائية للبنين - نظام الإدارة المدرسية الإلكتروني') ?>">
    <title><?= __('مدرسة بعشيقة الابتدائية للبنين') ?> - <?= __('نظام الإدارة المدرسية') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a7431;
            --primary-light: #2e9e47;
            --primary-dark: #0d4a1c;
            --secondary: #f8b500;
            --accent: #00897b;
            --bg-light: #f0f7f1;
            --text-dark: #1a1a2e;
            --text-light: #4a5568;
            --white: #ffffff;
            --shadow: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 25px 60px rgba(0,0,0,0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           شريط التنقل العلوي - Premium Navbar
        ═══════════════════════════════════════════════════════════════ */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 1rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0,0,0,0.08);
            padding: 0.75rem 3rem;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }
        
        .navbar-brand .logo {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            box-shadow: 0 8px 25px rgba(26, 116, 49, 0.35);
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover .logo {
            transform: scale(1.05) rotate(-3deg);
        }
        
        .navbar-brand .brand-text h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
            transition: color 0.3s;
        }
        
        .navbar-brand .brand-text small {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .navbar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn-nav {
            padding: 0.85rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 20px rgba(26, 116, 49, 0.35);
        }
        
        .btn-nav:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(26, 116, 49, 0.45);
        }
        
        .btn-nav-outline {
            padding: 0.75rem 1.5rem;
            background: rgba(255,255,255,0.9);
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-nav-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           القسم الرئيسي - Hero Section
        ═══════════════════════════════════════════════════════════════ */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 8rem 2rem 5rem;
            position: relative;
            overflow: hidden;
            background: 
                linear-gradient(
                    135deg,
                    rgba(240, 247, 241, 0.75) 0%,
                    rgba(200, 230, 210, 0.65) 50%,
                    rgba(180, 220, 195, 0.70) 100%
                ),
                url('assets/images/welcome.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        /* الأشكال الهندسية المتحركة */
        .hero-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
        }
        
        .shape-1 {
            width: 400px;
            height: 400px;
            background: var(--primary);
            top: -100px;
            right: -100px;
            animation: float1 20s ease-in-out infinite;
        }
        
        .shape-2 {
            width: 300px;
            height: 300px;
            background: var(--secondary);
            bottom: 10%;
            left: -50px;
            animation: float2 15s ease-in-out infinite;
        }
        
        .shape-3 {
            width: 200px;
            height: 200px;
            background: var(--accent);
            top: 40%;
            right: 10%;
            animation: float3 18s ease-in-out infinite;
        }
        
        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-30px, 30px) rotate(180deg); }
        }
        
        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, -20px) scale(1.1); }
        }
        
        @keyframes float3 {
            0%, 100% { transform: translate(0, 0); }
            33% { transform: translate(20px, 20px); }
            66% { transform: translate(-20px, 10px); }
        }
        
        /* شعار المدرسة */
        .school-badge {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: center;
        }
        
        .school-emblem {
            width: 150px;
            height: 150px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            box-shadow: 
                0 20px 50px rgba(26, 116, 49, 0.4),
                0 0 0 10px rgba(26, 116, 49, 0.1),
                0 0 0 20px rgba(26, 116, 49, 0.05);
            animation: badgePulse 3s ease-in-out infinite;
        }
        
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        /* العناوين */
        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
        }
        
        .hero h1 {
            font-size: 3.2rem;
            font-weight: 900;
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
            text-shadow: 0 4px 20px rgba(255,255,255,0.8);
            letter-spacing: -0.5px;
        }
        
        .hero .subtitle {
            font-size: 1.6rem;
            color: var(--primary);
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 0 2px 15px rgba(255,255,255,0.9);
        }
        
        .hero .location {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: 2.5rem;
            font-weight: 600;
            background: rgba(255,255,255,0.7);
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }
        
        .hero-description {
            max-width: 700px;
            font-size: 1.2rem;
            line-height: 2;
            color: var(--text-dark);
            margin-bottom: 3rem;
            font-weight: 500;
            background: rgba(255,255,255,0.6);
            padding: 2rem 2.5rem;
            border-radius: 25px;
            backdrop-filter: blur(15px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }
        
        /* أزرار CTA */
        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-primary-hero {
            padding: 1.2rem 3rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 60px;
            font-size: 1.2rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 35px rgba(26, 116, 49, 0.4);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .btn-primary-hero:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 45px rgba(26, 116, 49, 0.5);
        }
        
        .btn-secondary-hero {
            padding: 1.2rem 3rem;
            background: rgba(255,255,255,0.95);
            color: var(--primary);
            border: 3px solid var(--primary);
            border-radius: 60px;
            font-size: 1.2rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary-hero:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-5px);
        }
        

        
        /* ═══════════════════════════════════════════════════════════════
           قسم الميزات - Features Section
        ═══════════════════════════════════════════════════════════════ */
        .features {
            padding: 6rem 2rem;
            background: linear-gradient(180deg, var(--white) 0%, var(--bg-light) 100%);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-badge {
            display: inline-block;
            background: linear-gradient(135deg, rgba(26, 116, 49, 0.1), rgba(26, 116, 49, 0.05));
            color: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 800;
        }
        
        .section-header p {
            color: var(--text-light);
            font-size: 1.15rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bg-light) 0%, rgba(26, 116, 49, 0.1) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(-5deg);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }
        
        .feature-card h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            font-weight: 700;
        }
        
        .feature-card p {
            color: var(--text-light);
            line-height: 1.8;
            font-size: 0.95rem;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           قسم الإحصائيات - Stats Section
        ═══════════════════════════════════════════════════════════════ */
        .stats {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .stats::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            animation: patternMove 60s linear infinite;
        }
        
        @keyframes patternMove {
            100% { transform: translate(50%, 50%); }
        }
        
        .stats-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2.5rem;
            position: relative;
            z-index: 1;
        }
        
        .stat-card {
            text-align: center;
            color: white;
            padding: 2rem;
            background: rgba(255,255,255,0.08);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.12);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .stat-value {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #a5d6a7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 1.05rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        /* ═══════════════════════════════════════════════════════════════
           قسم طلب النسخة التجريبية - Free Trial CTA
        ═══════════════════════════════════════════════════════════════ */
        .cta-section {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M54.627 0l.83.828-1.415 1.415L51.8 0h2.827zM5.373 0l-.83.828L5.96 2.243 8.2 0H5.374zM48.97 0l3.657 3.657-1.414 1.414L46.143 0h2.828zM11.03 0L7.372 3.657 8.787 5.07 13.857 0H11.03zm32.284 0L49.8 6.485 48.384 7.9l-7.9-7.9h2.83zM16.686 0L10.2 6.485 11.616 7.9l7.9-7.9h-2.83zM22.344 0L13.858 8.485 15.272 9.9l7.9-7.9h-.828zm5.656 0L19.515 8.485 20.93 9.9l8.485-8.485h-1.414zM32 0l-8.485 8.485L24.93 9.9 34 .828 32.586 0H32zm3.657 0l10.606 10.606-1.414 1.414L43.435 0h-7.778zM38.828 0L50.343 11.515l-1.414 1.414L36 0h2.828z' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E");
        }
        
        .cta-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .cta-section h2 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }
        
        .cta-section p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            line-height: 1.8;
        }
        
        .cta-buttons-row {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .btn-telegram {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.1rem 2.5rem;
            background: linear-gradient(135deg, #0088cc 0%, #0077b5 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.15rem;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 136, 204, 0.4);
        }
        
        .btn-telegram:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 136, 204, 0.5);
        }
        
        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.1rem 2.5rem;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.15rem;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
        }
        
        .btn-whatsapp:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(37, 211, 102, 0.5);
        }
        
        .btn-email-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.1rem 2.5rem;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 2px solid rgba(255,255,255,0.5);
            border-radius: 50px;
            font-size: 1.15rem;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .btn-email-cta:hover {
            background: white;
            color: #764ba2;
            border-color: white;
        }
        
        /* زر التواصل العائم */
        .floating-contact {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .floating-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .floating-btn.telegram {
            background: linear-gradient(135deg, #0088cc 0%, #0077b5 100%);
            color: white;
        }
        
        .floating-btn.whatsapp {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
        }
        
        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        @media (max-width: 768px) {
            .cta-section h2 {
                font-size: 1.8rem;
            }
            
            .cta-buttons-row {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-telegram, .btn-whatsapp, .btn-email-cta {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .floating-contact {
                bottom: 1rem;
                left: 1rem;
            }
            
            .floating-btn {
                width: 48px;
                height: 48px;
                font-size: 1.3rem;
            }
        }
        
        /* ═══════════════════════════════════════════════════════════════
           قسم شهادات المستخدمين - Testimonials Section
        ═══════════════════════════════════════════════════════════════ */
        .testimonials {
            padding: 6rem 2rem;
            background: linear-gradient(180deg, var(--bg-light) 0%, var(--white) 100%);
        }
        
        .testimonials-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }
        
        .testimonial-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 5rem;
            color: var(--primary);
            opacity: 0.1;
            font-family: Georgia, serif;
            line-height: 1;
        }
        
        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.12);
        }
        
        .testimonial-content {
            margin-bottom: 1.5rem;
        }
        
        .testimonial-content p {
            color: var(--text-dark);
            line-height: 1.9;
            font-size: 1rem;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        
        .author-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(26, 116, 49, 0.3);
        }
        
        .author-info {
            display: flex;
            flex-direction: column;
        }
        
        .author-info strong {
            color: var(--text-dark);
            font-size: 1rem;
            font-weight: 700;
        }
        
        .author-info span {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .testimonials {
                padding: 4rem 1.5rem;
            }
            
            .testimonials-grid {
                grid-template-columns: 1fr;
            }
            
            .testimonial-card {
                padding: 1.5rem;
            }
        }
        
        /* ═══════════════════════════════════════════════════════════════
           التذييل - Footer
        ═══════════════════════════════════════════════════════════════ */
        .footer {
            padding: 3rem 2rem;
            text-align: center;
            background: var(--white);
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .footer-brand {
            font-size: 1.3rem;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--primary-dark);
        }
        
        .footer .copyright {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        /* ═══════════════════════════════════════════════════════════════
           Responsive Design
        ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0.6rem 1rem;
            }
            
            .navbar-brand .brand-text {
                display: none;
            }
            
            .navbar-brand .logo {
                width: 42px;
                height: 42px;
                font-size: 1.3rem;
                border-radius: 12px;
            }
            
            .navbar-actions {
                gap: 0.5rem;
            }
            
            .btn-nav {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
                border-radius: 30px;
                box-shadow: 0 4px 12px rgba(26, 116, 49, 0.25);
            }
            
            .btn-nav-outline {
                padding: 0.55rem 0.9rem;
                font-size: 0.85rem;
                border-width: 2px;
            }
            
            .hero {
                padding: 11rem 1.5rem 4rem;
                background-attachment: scroll;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero .subtitle {
                font-size: 1.2rem;
            }
            
            .school-emblem {
                width: 120px;
                height: 120px;
                font-size: 3rem;
            }
            
            .hero-description {
                padding: 1.5rem;
                font-size: 1rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 320px;
                align-items: center;
                margin: 0 auto;
                gap: 1rem;
            }
            
            .btn-primary-hero, .btn-secondary-hero {
                width: 100%;
                justify-content: center;
                padding: 1rem 2rem;
                text-align: center;
                margin: 0;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header h2 {
                font-size: 2rem;
            }
            
            .scroll-indicator {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .stat-card {
                padding: 1.5rem 1rem;
            }
            
            .stat-value {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar" id="navbar">
        <a href="<?= $baseUrl ?>" class="navbar-brand">
            <div class="logo">🏫</div>
            <div class="brand-text">
                <h1><?= __('مدرسة بعشيقة الابتدائية للبنين') ?></h1>
                <small><?= __('نظام الإدارة المدرسية') ?></small>
            </div>
        </a>
        <div class="navbar-actions">
            <a href="<?= $baseUrl ?>controllers/language_handler.php?lang=<?= $currentLang === 'ar' ? 'en' : 'ar' ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
               class="btn-nav-outline" title="<?= $currentLang === 'ar' ? 'English' : 'العربية' ?>">
                <?= $currentLang === 'ar' ? '🌐 EN' : '🌐 عربي' ?>
            </a>
            <?php if ($isLoggedIn): ?>
                <a href="<?= $baseUrl ?>dashboard" class="btn-nav"><?= __('📊 لوحة التحكم') ?></a>
            <?php else: ?>
                <a href="<?= $baseUrl ?>login" class="btn-nav"><?= __('🔐 تسجيل الدخول') ?></a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- القسم الرئيسي -->
    <section class="hero">
        <!-- الأشكال الهندسية -->
        <div class="hero-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
        
        <div class="hero-content">
            <!-- شعار المدرسة -->
            <div class="school-badge">
                <div class="school-emblem">🎓</div>
            </div>
            
            <h1><?= __('مدرسة بعشيقة الابتدائية للبنين') ?></h1>
            <p class="subtitle"><?= __('نحو جيل واعٍ ومستقبل مشرق ✨') ?></p>
            <p class="location"><?= __('📍 بعشيقة - نينوى - العراق') ?></p>
            
            <p class="hero-description">
                <?= __('نظام إدارة مدرسية متكامل وحديث يوفر أدوات احترافية لإدارة شؤون الطلاب والكادر التعليمي،') ?>
                <?= __('تسجيل الحضور والغياب، رصد الدرجات، توزيع المواد، جرد الأثاث، إدارة الإجازات،') ?>
                <?= __('وإصدار التقارير المتنوعة بكل سهولة ويسر. يدعم اللغتين العربية والإنجليزية.') ?>
            </p>
            
            <div class="cta-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $baseUrl ?>dashboard" class="btn-primary-hero">
                        <span>📊</span> <?= __('الدخول للوحة التحكم') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>login" class="btn-primary-hero">
                        <span>🔐</span> <?= __('تسجيل الدخول للنظام') ?>
                    </a>
                <?php endif; ?>
                <a href="#features" class="btn-secondary-hero">
                    <span>📖</span> <?= __('تعرف على النظام') ?>
                </a>
            </div>
        </div>
        

    </section>
    
    <!-- قسم الميزات -->
    <section class="features" id="features">
        <div class="section-header">
            <span class="section-badge"><?= __('🌟 مميزات النظام') ?></span>
            <h2><?= __('نظام متكامل وشامل') ?></h2>
            <p><?= __('نظام متكامل وشامل لجميع احتياجات الإدارة المدرسية') ?></p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">👨‍🎓</div>
                <h3><?= __('إدارة الطلاب') ?></h3>
                <p><?= __('بطاقات مدرسية شاملة، سجلات أكاديمية، صور الطلاب، وإمكانية إنشاء حسابات دخول لكل طالب') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3><?= __('تسجيل الحضور') ?></h3>
                <p><?= __('نظام متطور لتسجيل حضور وغياب الطلاب لكل حصة دراسية مع إحصائيات دقيقة وتقارير يومية') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👔</div>
                <h3><?= __('إدارة الكادر التعليمي') ?></h3>
                <p><?= __('ملفات المعلمين الكاملة، تسجيل الدوام والغيابات، إدارة الإجازات، وتقارير الأداء') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📝</div>
                <h3><?= __('رصد الدرجات') ?></h3>
                <p><?= __('نظام رصد درجات متكامل حسب المنهج العراقي، كشوفات النتائج، وتقارير الأداء الأكاديمي') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h3><?= __('توزيع المواد والصفوف') ?></h3>
                <p><?= __('نظام ذكي لتوزيع المعلمين على المواد والصفوف والشعب مع التحكم في صلاحيات الرصد') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🪑</div>
                <h3><?= __('جرد أثاث المدرسة') ?></h3>
                <p><?= __('نظام متكامل لجرد وإدارة أثاث الصفوف والمرافق المدرسية مع تقارير مفصلة') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3><?= __('التقارير والإحصائيات') ?></h3>
                <p><?= __('تقارير شاملة عن الحضور، الغياب، الدرجات، والأداء بصيغ PDF و Excel') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3><?= __('التقويم والمناسبات') ?></h3>
                <p><?= __('تقويم مدرسي تفاعلي لعرض المناسبات والأحداث المهمة والعطل الرسمية') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🌐</div>
                <h3><?= __('دعم اللغتين') ?></h3>
                <p><?= __('واجهة ثنائية اللغة (العربية والإنجليزية) مع إمكانية التبديل الفوري') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">💾</div>
                <h3><?= __('النسخ الاحتياطي') ?></h3>
                <p><?= __('نظام نسخ احتياطي تلقائي لقاعدة البيانات والصور مع إمكانية الاستعادة') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📜</div>
                <h3><?= __('سجل العمليات') ?></h3>
                <p><?= __('تتبع جميع العمليات في النظام مع معلومات المستخدم والتاريخ والتفاصيل') ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3><?= __('أمان متقدم') ?></h3>
                <p><?= __('حماية ضد SQL Injection و XSS و CSRF، تشفير كلمات المرور، ونظام صلاحيات متعدد') ?></p>
            </div>
        </div>
    </section>
    
    <!-- قسم الإحصائيات -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📚</span>
                <div class="stat-value">6</div>
                <div class="stat-label"><?= __('صفوف دراسية') ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">⚙️</span>
                <div class="stat-value">12+</div>
                <div class="stat-label"><?= __('ميزة متكاملة') ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">👤</span>
                <div class="stat-value">4</div>
                <div class="stat-label"><?= __('أنواع مستخدمين') ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🌐</span>
                <div class="stat-value">2</div>
                <div class="stat-label"><?= __('لغات مدعومة') ?></div>
            </div>
        </div>
    </section>
    
    <!-- قسم طلب النسخة التجريبية -->
    <section class="cta-section" id="contact">
        <div class="cta-content">
            <h2><?= __('🚀 جرّب النظام الآن مجاناً!') ?></h2>
            <p>
                <?= __('هل ترغب بتجربة النظام قبل الاقتناء؟ تواصل معنا الآن واحصل على نسخة تجريبية لتستكشف جميع الميزات.') ?>
                <br>
                <?= __('نحن هنا لمساعدتك في إدارة مدرستك بأفضل طريقة ممكنة.') ?>
            </p>
            <div class="cta-buttons-row">
                <a href="https://t.me/wtrul4" target="_blank" class="btn-telegram">
                    <span>📱</span> <?= __('تواصل عبر تيليجرام') ?>
                </a>
                <a href="https://wa.me/491773988092" target="_blank" class="btn-whatsapp">
                    <span>💬</span> <?= __('تواصل عبر واتساب') ?>
                </a>
                <a href="mailto:aseel.marwan.kheder@gmail.com" class="btn-email-cta">
                    <span>📧</span> <?= __('راسلنا بالإيميل') ?>
                </a>
            </div>
        </div>
    </section>
    
    <!-- قسم انطباعات المختبرين -->
    <section class="testimonials" id="testimonials">
        <div class="section-header">
            <span class="section-badge"><?= __('✨ انطباعات أولية') ?></span>
            <h2><?= __('ما لاحظه المختبرون') ?></h2>
            <p><?= __('ملاحظات من معلمين ومعاونين جربوا النظام خلال مرحلة الاختبار') ?></p>
        </div>
        
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"<?= __('الواجهة مريحة للعين وسهلة الفهم. تسجيل حضور الطلاب يتم بسرعة كبيرة.') ?>"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👨‍💼</div>
                    <div class="author-info">
                        <strong><?= __('معاون مدير') ?></strong>
                        <span><?= __('مختبر للنظام') ?></span>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"<?= __('رصد الدرجات والتقارير التلقائية توفر وقتاً كبيراً مقارنة بالعمل اليدوي.') ?>"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👨‍🏫</div>
                    <div class="author-info">
                        <strong><?= __('معلم') ?></strong>
                        <span><?= __('مختبر للنظام') ?></span>
                    </div>
                </div>
            </div>
            
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p>"<?= __('يعمل بشكل جيد على الهاتف والحاسوب. دعم اللغتين ميزة عملية جداً.') ?>"</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">👨‍💻</div>
                    <div class="author-info">
                        <strong><?= __('مستخدم') ?></strong>
                        <span><?= __('مختبر للنظام') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- أزرار التواصل العائمة -->
    <div class="floating-contact">
        <a href="https://t.me/wtrul4" target="_blank" class="floating-btn telegram" title="<?= __('تواصل عبر تيليجرام') ?>">
            📱
        </a>
        <a href="https://wa.me/491773988092" target="_blank" class="floating-btn whatsapp" title="<?= __('تواصل عبر واتساب') ?>">
            💬
        </a>
    </div>
    
    <!-- التذييل -->
    <footer class="footer">
        <p class="footer-brand">🏫 <?= __('مدرسة بعشيقة الابتدائية للبنين') ?> - <?= __('نظام الإدارة المدرسية') ?></p>
        <div class="footer-links">
            <a href="<?= $baseUrl ?>privacy">🔒 <?= __('سياسة الخصوصية') ?></a>
            <a href="mailto:aseel.marwan.kheder@gmail.com">📧 <?= __('تواصل معنا') ?></a>
        </div>
        <p class="copyright">© <?= date('Y') ?> <?= __('جميع الحقوق محفوظة') ?> - <?= __('مديرية تربية نينوى') ?></p>
    </footer>
    
    <script>
        // تأثير الـ navbar عند التمرير
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // التمرير السلس
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
        
        // تأثير الظهور عند التمرير
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
        
        document.querySelectorAll('.feature-card, .stat-card, .testimonial-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(el);
        });
    </script>
</body>
</html>
