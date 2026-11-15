@extends('layouts.public')

@section('title', 'Layanan Tanda Tangan Elektronik (TTE) & Email Dinas — Pemerintah Kota Cirebon')

@section('body_style', 'background-color: #ffffff; color: #1e293b; font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif; overflow-x: hidden;')

@push('styles')
<!-- Fast Google Fonts CDN with display=swap (Zero blocking) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Lightweight RemixIcon CDN (Single edge-cached stylesheet) -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">

<style>
    :root {
        --sky-blue: #1d68f2;
        --sky-blue-hover: #1555c7;
        --sky-blue-light: #eaf2ff;
        --sky-blue-subtle: #f0f6ff;
        --dark-pill: #111827;
        --dark-pill-hover: #1f2937;
        --slate-text: #64748b;
        --slate-title: #0f172a;
        --border-subtle: #f1f5f9;
        --card-radius: 32px;
        --hero-radius: 40px;
    }

    * { box-sizing: border-box; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; background: #ffffff; }
    h1, h2, h3, .font-heading { font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif; }

    /* ── A. MODERN NAVBAR ────────────────────────────────────────── */
    .travel-navbar {
        max-width: 1240px;
        margin: 0 auto;
        padding: 1.5rem 1.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
    }
    .brand-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
    }
    .brand-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--sky-blue);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: 0 4px 14px rgba(29, 104, 242, 0.3);
    }
    .brand-text {
        font-family: 'Outfit', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--slate-title);
        letter-spacing: -0.01em;
        line-height: 1.1;
    }
    .brand-sub {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: var(--sky-blue);
        text-transform: uppercase;
    }
    .nav-links {
        display: flex;
        align-items: center;
        gap: 2rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .nav-links a {
        text-decoration: none;
        color: #475569;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        transition: color 0.2s ease;
    }
    .nav-links a:hover {
        color: var(--sky-blue);
    }
    .btn-pill-dark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: var(--dark-pill);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 0.75rem 1.65rem;
        border-radius: 9999px;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(17, 24, 39, 0.15);
    }
    .btn-pill-dark:hover {
        background: var(--dark-pill-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(17, 24, 39, 0.22);
    }

    /* ── B. HERO SECTION (REFERENCE MATCH) ────────────────────────── */
    .hero-outer {
        max-width: 1240px;
        margin: 0 auto;
        padding: 0 1.25rem 2rem;
    }
    .hero-card-modern {
        background: linear-gradient(135deg, #dbeafe 0%, #edf4fe 45%, #e2eefd 100%);
        border-radius: var(--hero-radius);
        padding: 3.5rem 3.5rem 3.75rem;
        position: relative;
        overflow: hidden;
        min-height: 520px;
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        gap: 2rem;
        align-items: center;
        box-shadow: 0 20px 40px -15px rgba(29, 104, 242, 0.08);
    }
    /* Subtle cloud graphics inside hero background */
    .hero-cloud-bg {
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle at 85% 30%, rgba(255,255,255,0.85) 0%, rgba(255,255,255,0) 55%),
                          radial-gradient(circle at 15% 80%, rgba(255,255,255,0.7) 0%, rgba(255,255,255,0) 50%);
        pointer-events: none;
        z-index: 1;
    }
    .hero-content {
        position: relative;
        z-index: 2;
        display: flex;
        gap: 1.75rem;
    }
    /* Left Vertical Step Rail (1, 2, 3 as in Reference Image) */
    .step-rail {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.75rem;
        position: relative;
        padding-top: 0.35rem;
    }
    .step-rail::before {
        content: '';
        position: absolute;
        top: 15px;
        bottom: 15px;
        width: 1.5px;
        background: rgba(29, 104, 242, 0.25);
        z-index: 0;
    }
    .step-dot {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6875rem;
        font-weight: 800;
        background: #ffffff;
        color: var(--sky-blue);
        border: 2px solid var(--sky-blue);
        position: relative;
        z-index: 1;
        box-shadow: 0 2px 6px rgba(29, 104, 242, 0.15);
    }
    .step-dot.active {
        background: var(--sky-blue);
        color: #ffffff;
    }
    .hero-main-text {
        flex: 1;
    }
    .eyebrow-tag {
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--sky-blue);
        margin-bottom: 0.75rem;
        display: block;
    }
    .hero-title {
        font-size: clamp(2.35rem, 4.2vw, 3.4rem);
        font-weight: 900;
        line-height: 1.1;
        letter-spacing: -0.03em;
        color: var(--slate-title);
        margin: 0 0 1.25rem 0;
    }
    .hero-subtitle {
        font-size: 0.9375rem;
        line-height: 1.6;
        color: #475569;
        margin: 0 0 2rem 0;
        max-width: 480px;
    }
    .hero-actions {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        flex-wrap: wrap;
    }
    .btn-pill-blue {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        background: var(--sky-blue);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.9375rem;
        padding: 0.9rem 1.85rem;
        border-radius: 9999px;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 8px 20px -3px rgba(29, 104, 242, 0.35);
    }
    .btn-pill-blue:hover {
        background: var(--sky-blue-hover);
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -3px rgba(29, 104, 242, 0.45);
    }
    .btn-pill-white {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #ffffff;
        color: var(--slate-title) !important;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 0.85rem 1.45rem;
        border-radius: 9999px;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    .btn-pill-white:hover {
        background: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.09);
    }
    .btn-circle-action {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #ffffff;
        color: var(--sky-blue);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.2s ease;
    }
    .btn-circle-action:hover {
        transform: scale(1.08);
        color: var(--sky-blue-hover);
        box-shadow: 0 6px 16px rgba(29, 104, 242, 0.2);
    }

    /* Hero Right Visual & Cutout Floating Card (Know More →) */
    .hero-visual-area {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
        min-height: 380px;
    }
    .hero-floating-graphic {
        width: 100%;
        max-width: 440px;
        height: auto;
        transform: perspective(1000px) rotateY(-8deg) rotateX(4deg) translateY(-10px);
        filter: drop-shadow(0 25px 30px rgba(15, 23, 42, 0.18));
        border-radius: 28px;
        transition: transform 0.4s ease;
    }
    .hero-floating-graphic:hover {
        transform: perspective(1000px) rotateY(-2deg) rotateX(1deg) translateY(-14px);
    }
    /* Cutout pill badge in bottom-right corner matching the reference image */
    .hero-cutout-card {
        position: absolute;
        bottom: -15px;
        right: -15px;
        background: #ffffff;
        border-radius: 28px;
        padding: 1.15rem 1.65rem;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 1.15rem;
        z-index: 4;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    .cutout-cta-text {
        font-size: 0.875rem;
        font-weight: 800;
        color: var(--slate-title);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .avatar-stack {
        display: flex;
        align-items: center;
    }
    .avatar-stack img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        margin-left: -10px;
        object-fit: cover;
    }
    .avatar-stack img:first-child { margin-left: 0; }
    .cutout-meta {
        font-size: 0.6875rem;
        color: var(--slate-text);
        line-height: 1.25;
    }

    /* ── C. BRAND / PARTNER CLOUD RIBBON ──────────────────────────── */
    .partner-ribbon {
        max-width: 1240px;
        margin: 1.5rem auto 3.5rem;
        padding: 0 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 2rem;
    }
    .partner-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }
    .partner-logos {
        display: flex;
        align-items: center;
        gap: 2.25rem;
        flex-wrap: wrap;
    }
    .partner-logo-item {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 700;
        color: #64748b;
        letter-spacing: -0.01em;
        transition: color 0.2s ease;
    }
    .partner-logo-item:hover { color: var(--sky-blue); }

    /* ── D. FEATURED SECTION ("Popular Destination") ──────────────── */
    .section-popular {
        max-width: 1240px;
        margin: 0 auto 4.5rem;
        padding: 0 1.25rem;
    }
    .popular-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 2rem;
    }
    .popular-header h2 {
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--slate-title);
        margin: 0 0 0.25rem 0;
        letter-spacing: -0.02em;
    }
    .popular-header p {
        font-size: 0.875rem;
        color: var(--slate-text);
        margin: 0;
    }
    .nav-arrows {
        display: flex;
        gap: 0.5rem;
    }
    .btn-arrow {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: var(--slate-title);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-arrow.active, .btn-arrow:hover {
        background: var(--dark-pill);
        color: #ffffff;
        border-color: var(--dark-pill);
    }

    .destination-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.75rem;
    }
    .destination-card {
        background: #ffffff;
        border-radius: 28px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #f1f5f9;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
    }
    .destination-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 32px rgba(29, 104, 242, 0.09);
    }
    .card-img-wrap {
        height: 190px;
        overflow: hidden;
        position: relative;
    }
    .card-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    .destination-card:hover .card-img-wrap img {
        transform: scale(1.05);
    }
    .card-body-popular {
        padding: 1.35rem 1.5rem 1.5rem;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .card-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.35rem;
    }
    .card-title-popular {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--slate-title);
        margin: 0;
    }
    .rating-pill {
        background: #eff6ff;
        color: var(--sky-blue);
        font-weight: 800;
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .card-location {
        font-size: 0.75rem;
        color: var(--slate-text);
        display: flex;
        align-items: center;
        gap: 0.25rem;
        margin-bottom: 1.15rem;
    }
    .card-cta-btn {
        margin-top: auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.7rem 1.25rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-card-primary {
        background: var(--sky-blue);
        color: #ffffff;
    }
    .btn-card-primary:hover {
        background: var(--sky-blue-hover);
    }
    .btn-card-outline {
        background: #f8fafc;
        color: var(--slate-title);
        border: 1px solid #e2e8f0;
    }
    .btn-card-outline:hover {
        background: #f1f5f9;
    }

    /* ── E. JOURNEY / WORKFLOW ("Journey To The Skies Made Simple!") ── */
    .section-journey {
        max-width: 1240px;
        margin: 0 auto 5rem;
        padding: 0 1.25rem;
        text-align: center;
    }
    .journey-title {
        font-size: 2.15rem;
        font-weight: 800;
        color: var(--slate-title);
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.02em;
    }
    .journey-subtitle {
        font-size: 0.9375rem;
        color: var(--slate-text);
        margin: 0 auto 3rem;
        max-width: 580px;
    }
    .journey-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.75rem;
        text-align: left;
    }
    .journey-card {
        border-radius: 30px;
        padding: 2.5rem 2rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 340px;
        transition: transform 0.3s ease;
    }
    .journey-card:hover {
        transform: translateY(-6px);
    }
    /* Default light cards */
    .journey-card-light {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
    }
    .journey-icon-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sky-blue);
        font-size: 1.25rem;
        margin-bottom: 2rem;
    }
    .journey-card-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--slate-title);
        margin: 0 0 0.75rem 0;
    }
    .journey-card-desc {
        font-size: 0.875rem;
        color: var(--slate-text);
        line-height: 1.6;
        margin: 0;
    }
    /* THE SIGNATURE BLUE CARD (exact match to reference) */
    .journey-card-blue {
        background: linear-gradient(135deg, #1d68f2 0%, #1656c9 100%);
        color: #ffffff;
        box-shadow: 0 20px 35px -10px rgba(29, 104, 242, 0.4);
        position: relative;
        overflow: hidden;
    }
    .journey-card-blue::after {
        content: '';
        position: absolute;
        top: -60px;
        right: -60px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
        pointer-events: none;
    }
    .journey-icon-blue {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.25rem;
        margin-bottom: 2rem;
    }
    .journey-card-blue .journey-card-title {
        color: #ffffff;
    }
    .journey-card-blue .journey-card-desc {
        color: rgba(255, 255, 255, 0.88);
    }
    .link-learn-more {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #ffffff;
        font-size: 0.8125rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-decoration: none;
        margin-top: 1.5rem;
        transition: gap 0.2s ease;
    }
    .link-learn-more:hover { gap: 0.65rem; }

    /* ── F. LARGE BANNER ("UNLEASH WANDERLUST WITH SKYWINGS") ─────── */
    .section-unleash {
        max-width: 1240px;
        margin: 0 auto 5rem;
        padding: 0 1.25rem;
    }
    .unleash-card {
        background: #f8fafc;
        border-radius: var(--hero-radius);
        padding: 3.5rem;
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 3.5rem;
        align-items: center;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }
    .unleash-image-wrap {
        border-radius: 30px;
        overflow: hidden;
        height: 420px;
        position: relative;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
    }
    .unleash-image-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .unleash-badge-floating {
        position: absolute;
        bottom: 20px;
        left: 20px;
        background: #ffffff;
        border-radius: 20px;
        padding: 0.875rem 1.35rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
    }
    .unleash-badge-floating .badge-val {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 900;
        color: var(--sky-blue);
    }
    .unleash-badge-floating .badge-lbl {
        font-size: 0.6875rem;
        font-weight: 700;
        color: var(--slate-text);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .unleash-content h2 {
        font-size: clamp(2rem, 3.5vw, 2.85rem);
        font-weight: 900;
        color: var(--slate-title);
        line-height: 1.15;
        margin: 0 0 1.5rem 0;
        letter-spacing: -0.02em;
    }
    .unleash-content p {
        font-size: 0.9375rem;
        line-height: 1.7;
        color: var(--slate-text);
        margin: 0 0 1.75rem 0;
    }
    .unleash-stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        margin-bottom: 2.25rem;
        background: #ffffff;
        border-radius: 20px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
    }
    .stat-mini-val {
        font-family: 'Outfit', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--slate-title);
    }
    .stat-mini-lbl {
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--slate-text);
        margin-top: 2px;
    }
    .btn-pill-large {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--dark-pill);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 1rem;
        padding: 1.05rem 2.25rem;
        border-radius: 9999px;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 8px 20px rgba(17, 24, 39, 0.2);
    }
    .btn-pill-large:hover {
        background: var(--dark-pill-hover);
        transform: translateY(-2px);
        box-shadow: 0 12px 25px rgba(17, 24, 39, 0.28);
    }

    /* ── G. INTERACTIVE SKPD ADOPTION EXPLORER ────────────────────── */
    .section-explorer {
        max-width: 1240px;
        margin: 0 auto 5rem;
        padding: 0 1.25rem;
    }
    .explorer-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        border: 1px solid #e2e8f0;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    }
    .explorer-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    .explorer-search-input {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 9999px;
        padding: 0.75rem 1.35rem 0.75rem 2.75rem;
        font-size: 0.875rem;
        width: 340px;
        max-width: 100%;
        outline: none;
        transition: all 0.2s ease;
    }
    .explorer-search-input:focus {
        border-color: var(--sky-blue);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(29, 104, 242, 0.15);
    }
    .filter-pills-row {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }
    .filter-btn {
        background: #f1f5f9;
        border: none;
        border-radius: 9999px;
        padding: 0.45rem 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .filter-btn.active, .filter-btn:hover {
        background: var(--sky-blue);
        color: #ffffff;
    }

    /* Modern Minimalist Table */
    .table-modern {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 0.5rem;
    }
    .table-modern th {
        font-size: 0.6875rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        padding: 0.75rem 1rem;
        text-align: left;
    }
    .table-modern td {
        background: #f8fafc;
        padding: 1rem;
        font-size: 0.875rem;
        vertical-align: middle;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }
    .table-modern tr td:first-child {
        border-top-left-radius: 16px;
        border-bottom-left-radius: 16px;
        border-left: 1px solid #f1f5f9;
    }
    .table-modern tr td:last-child {
        border-top-right-radius: 16px;
        border-bottom-right-radius: 16px;
        border-right: 1px solid #f1f5f9;
    }
    .progress-bar-modern {
        background: #e2e8f0;
        height: 7px;
        border-radius: 9999px;
        overflow: hidden;
        width: 100%;
        max-width: 140px;
    }
    .progress-fill-modern {
        height: 100%;
        border-radius: 9999px;
        background: var(--sky-blue);
        transition: width 0.6s ease;
    }

    /* ── H. CLEAN MINIMALIST FOOTER ──────────────────────────────── */
    .footer-modern {
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        padding: 4rem 1.25rem 2.5rem;
    }
    .footer-inner {
        max-width: 1240px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
        gap: 3rem;
        margin-bottom: 3.5rem;
    }
    .footer-col h4 {
        font-size: 0.875rem;
        font-weight: 800;
        color: var(--slate-title);
        margin: 0 0 1.25rem 0;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .footer-col ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .footer-col ul li {
        margin-bottom: 0.65rem;
    }
    .footer-col ul li a {
        color: var(--slate-text);
        text-decoration: none;
        font-size: 0.8125rem;
        transition: color 0.2s ease;
    }
    .footer-col ul li a:hover {
        color: var(--sky-blue);
    }
    .footer-bottom {
        max-width: 1240px;
        margin: 0 auto;
        padding-top: 2rem;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.75rem;
        color: #94a3b8;
        flex-wrap: wrap;
        gap: 1rem;
    }

    /* Responsive Queries */
    @media (max-width: 992px) {
        .hero-card-modern {
            grid-template-columns: 1fr;
            padding: 2.5rem 2rem;
        }
        .hero-visual-area {
            justify-content: center;
            min-height: 280px;
        }
        .hero-cutout-card {
            position: relative;
            bottom: auto;
            right: auto;
            margin-top: 1.5rem;
        }
        .destination-grid, .journey-grid {
            grid-template-columns: 1fr;
        }
        .unleash-card {
            grid-template-columns: 1fr;
            padding: 2.25rem;
        }
        .footer-inner {
            grid-template-columns: 1fr 1fr;
        }
        .nav-links { display: none; }
    }
    @media (max-width: 640px) {
        .footer-inner { grid-template-columns: 1fr; }
        .hero-content { flex-direction: column; }
        .step-rail { display: none; }
        .unleash-stats-row { grid-template-columns: 1fr; }
    }
</style>
@endpush

{{-- Custom Header matching the reference top navigation --}}
@section('custom_header')
<header style="background: #ffffff; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; z-index: 50;">
    <div class="travel-navbar">
        <a href="{{ route('home') }}" class="brand-group">
            <img src="{{ asset('images/branding/logo-dkis-kota-cirebon.jpg') }}" 
                 alt="Logo DKIS Kota Cirebon" 
                 style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 12px rgba(29, 104, 242, 0.25); border: 2px solid #ffffff;">
            <div>
                <div class="brand-text">SISMON TTE</div>
                <div class="brand-sub">DKIS KOTA CIREBON</div>
            </div>
        </a>

        <ul class="nav-links">
            <li><a href="#hero">Beranda</a></li>
            <li><a href="#jalur-layanan">Layanan TTE</a></li>
            <li><a href="#alur-layanan">Alur Pengajuan</a></li>
            <li><a href="#skpd-explorer">Capaian SKPD</a></li>
            <li><a href="#tentang-tte">Tentang</a></li>
        </ul>

        <div>
            <a href="{{ route('pendaftaran.index') }}" class="btn-pill-dark">
                <span>Daftar TTE</span>
                <i class="ri-arrow-right-up-line"></i>
            </a>
        </div>
    </div>
</header>
@endsection

@section('content')
<div style="background: #ffffff;">

    <!-- ── 1. HERO SECTION (ULTRA-MODERN FIGMA AESTHETIC) ────────── -->
    <section class="hero-outer" id="hero">
        <div class="hero-card-modern">
            <div class="hero-cloud-bg"></div>

            <!-- Left Content with Vertical Step Rail -->
            <div class="hero-content">
                <!-- Vertical Progress Rail (1, 2, 3 as in Reference Image) -->
                <div class="step-rail">
                    <div class="step-dot active" title="Langkah 1: Registrasi Online">1</div>
                    <div class="step-dot" title="Langkah 2: Verifikasi Persandian DKIS">2</div>
                    <div class="step-dot" title="Langkah 3: Penerbitan Sertifikat BSrE">3</div>
                </div>

                <div class="hero-main-text">
                    <span class="eyebrow-tag">
                        DKIS KOTA CIREBON &bull; BSrE BSSN TERVERIFIKASI
                    </span>

                    <h1 class="hero-title">
                        Layanan Tanda Tangan Elektronik Sah &amp; Cepat!
                    </h1>

                    <p class="hero-subtitle">
                        <strong>Tingkat Adopsi TTE Aparatur Pemerintah Kota Cirebon.</strong> Akselerasi birokrasi digital resmi terintegrasi bagi ASN dan Non-ASN tanpa antrean fisik dan 100% bebas kertas.
                    </p>

                    <div class="hero-actions">
                        <a href="{{ route('pendaftaran.index', ['tipe' => 'ASN']) }}" class="btn-pill-blue">
                            <span>Formulir Pegawai ASN</span>
                            <i class="ri-arrow-right-line"></i>
                        </a>

                        <a href="{{ route('pendaftaran.index', ['tipe' => 'NON_ASN']) }}" class="btn-pill-white">
                            <span>Formulir Pegawai Non-ASN</span>
                        </a>

                        <a href="#alur-layanan" class="btn-circle-action" title="Pelajari Alur Pengajuan">
                            <i class="ri-play-fill"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Visual Graphic & Cutout Badge Card -->
            <div class="hero-visual-area">
                <img src="{{ asset('images/hero/Security.png') }}" 
                     alt="Keamanan Informasi & Sertifikat Digital TTE DKIS Kota Cirebon" 
                     class="hero-floating-graphic">

                <!-- Cutout Overlapping Card (Exact replica of 'Know More →' from Reference) -->
                <div class="hero-cutout-card">
                    <div class="avatar-stack">
                        <img src="{{ asset('images/hero/avatar1.jpg') }}" alt="Aparatur DKIS 1">
                        <img src="{{ asset('images/hero/avatar2.jpg') }}" alt="Aparatur DKIS 2">
                    </div>
                    <div>
                        <a href="#skpd-explorer" class="cutout-cta-text">
                            <span>Capaian Kepatuhan Kota</span>
                            <i class="ri-arrow-right-line"></i>
                        </a>
                        <div class="cutout-meta">
                            <strong>{{ number_format($macroStats['total_sertifikat']) }}+</strong> Sertifikat TTE Aktif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── 2. PARTNER / ECOSYSTEM RIBBON ──────────────────────────── -->
    <div class="partner-ribbon">
        <div class="partner-label">Didukung Resmi Oleh Jaringan Keamanan Nasional:</div>
        <div class="partner-logos">
            <span class="partner-logo-item">
                <img src="{{ asset('images/branding/bsre-balai-besar-sertikasi-elektronik-2024-seeklogo.png') }}" alt="BSrE BSSN" style="height: 32px; object-fit: contain;">
                <span>BSrE BSSN</span>
            </span>
            <span class="partner-logo-item">
                <i class="ri-government-line" style="color: #0f172a;"></i> Pemkot Cirebon
            </span>
            <span class="partner-logo-item">
                <i class="ri-broadcast-line" style="color: #10b981;"></i> DKIS Kota Cirebon
            </span>
            <span class="partner-logo-item">
                <i class="ri-global-line" style="color: #6366f1;"></i> SPBE Nasional
            </span>
            <span class="partner-logo-item">
                <i class="ri-lock-2-line" style="color: #f59e0b;"></i> Kominfo RI
            </span>
        </div>
    </div>

    <!-- ── 3. FEATURED SECTION ("Popular Destination") ────────────── -->
    <section class="section-popular" id="jalur-layanan">
        <div class="popular-header">
            <div>
                <h2>Pilihan Jalur Registrasi TTE</h2>
                <p>Pilih kategori permohonan sertifikat elektronik sesuai status kepegawaian Anda di lingkungan Pemerintah Kota Cirebon</p>
            </div>
            <div class="nav-arrows">
                <button type="button" class="btn-arrow" onclick="scrollDestinations(-1)" aria-label="Previous">‹</button>
                <button type="button" class="btn-arrow active" onclick="scrollDestinations(1)" aria-label="Next">›</button>
            </div>
        </div>

        <div class="destination-grid" id="destination-slider">
            <!-- Card 1: ASN -->
            <div class="destination-card">
                <div class="card-img-wrap" style="background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ asset('images/categories/asn-cover.svg') }}" alt="Pegawai ASN" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="card-body-popular">
                    <div class="card-title-row">
                        <h3 class="card-title-popular">Formulir Pegawai ASN</h3>
                        <span class="rating-pill">★ 4.9</span>
                    </div>
                    <div class="card-location">
                        <i class="ri-map-pin-line"></i> Seluruh SKPD &bull; Berbasis NIP 18 Digit
                    </div>
                    <p style="font-size: 0.8125rem; color: #64748b; line-height: 1.5; margin: 0 0 1.25rem 0;">
                        Dikhususkan bagi PNS dan PPPK Kota Cirebon. Penerbitan tanda tangan elektronik resmi berstandar Balai Sertifikasi Elektronik (BSrE).
                    </p>
                    <a href="{{ route('pendaftaran.index', ['tipe' => 'ASN']) }}" class="card-cta-btn btn-card-primary">
                        <span>Daftar Jalur ASN</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>

            <!-- Card 2: Non-ASN -->
            <div class="destination-card">
                <div class="card-img-wrap" style="background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ asset('images/categories/non-asn-cover.svg') }}" alt="Pegawai Non-ASN" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="card-body-popular">
                    <div class="card-title-row">
                        <h3 class="card-title-popular">Formulir Pegawai Non-ASN</h3>
                        <span class="rating-pill">★ 4.8</span>
                    </div>
                    <div class="card-location">
                        <i class="ri-map-pin-line"></i> Tenaga Kontrak / THL &bull; Tanpa NIP
                    </div>
                    <p style="font-size: 0.8125rem; color: #64748b; line-height: 1.5; margin: 0 0 1.25rem 0;">
                        Jalur khusus untuk aparatur Non-ASN, tenaga honorer, dan pegawai kontrak daerah dengan rekomendasi resmi kepala perangkat daerah.
                    </p>
                    <a href="{{ route('pendaftaran.index', ['tipe' => 'NON_ASN']) }}" class="card-cta-btn btn-card-outline">
                        <span>Daftar Jalur Non-ASN</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>

            <!-- Card 3: Email Kedinasan -->
            <div class="destination-card">
                <div class="card-img-wrap" style="background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ asset('images/categories/email-cover.avif') }}" alt="Email Dinas" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="card-body-popular">
                    <div class="card-title-row">
                        <h3 class="card-title-popular">Email Kedinasan Resmi</h3>
                        <span class="rating-pill">★ 5.0</span>
                    </div>
                    <div class="card-location">
                        <i class="ri-map-pin-line"></i> Domain @cirebonkota.go.id &bull; SSO
                    </div>
                    <p style="font-size: 0.8125rem; color: #64748b; line-height: 1.5; margin: 0 0 1.25rem 0;">
                        Penyediaan akun surel resmi Pemerintah Daerah Kota Cirebon yang terintegrasi langsung dengan identitas sertifikat elektronik Anda.
                    </p>
                    <a href="{{ route('pendaftaran.index') }}" class="card-cta-btn btn-card-outline">
                        <span>Buat Permohonan Terpadu</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ── 4. JOURNEY ("Journey To The Skies Made Simple!") ────────── -->
    <section class="section-journey" id="alur-layanan">
        <h2 class="journey-title">Alur Penerbitan TTE Semudah 3 Langkah!</h2>
        <p class="journey-subtitle">
            Proses permohonan hingga validasi sertifikat digital dirancang ringkas, aman, dan tanpa berkas fisik berbelit
        </p>

        <div class="journey-grid">
            <!-- Step 1 (Light Card) -->
            <div class="journey-card journey-card-light">
                <div>
                    <div class="journey-icon-circle">
                        <i class="ri-file-edit-line"></i>
                    </div>
                    <h3 class="journey-card-title">1. Isi Formulir Mandiri</h3>
                    <p class="journey-card-desc">
                        Pilih kategori ASN atau Non-ASN, lengkapi NIK KTP, nama lengkap, dan SKPD penempatan. Seluruh data diverifikasi secara otomatis tanpa perlu melampirkan berkas fisik.
                    </p>
                </div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">
                    Estimasi: &plusmn; 3 Menit
                </div>
            </div>

            <!-- Step 2 (SIGNATURE BLUE CARD AS IN REFERENCE) -->
            <div class="journey-card journey-card-blue">
                <div>
                    <div class="journey-icon-blue">
                        <i class="ri-shield-user-line"></i>
                    </div>
                    <h3 class="journey-card-title">2. Verifikasi DKIS Kota Cirebon</h3>
                    <p class="journey-card-desc">
                        Verifikator Bidang Persandian DKIS Kota Cirebon memeriksa kelayakan identitas pemohon dan meneruskan data terenkripsi ke Otoritas Sertifikasi BSrE BSSN.
                    </p>
                </div>
                <div>
                    <a href="#skpd-explorer" class="link-learn-more">
                        <span>Pelajari Standar BSrE</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>

            <!-- Step 3 (Light Card) -->
            <div class="journey-card journey-card-light">
                <div>
                    <div class="journey-icon-circle">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <h3 class="journey-card-title">3. Sertifikat Aktif &amp; Sah</h3>
                    <p class="journey-card-desc">
                        Sertifikat TTE resmi terbit dan aktif. Pasangan kunci kriptografi siap digunakan untuk menandatangani naskah dinas elektronik dan persuratan digital di seluruh SKPD.
                    </p>
                </div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em;">
                    Kekuatan Hukum UU ITE Penuh
                </div>
            </div>
        </div>
    </section>

    <!-- ── 5. LARGE SPLIT BANNER ("UNLEASH WANDERLUST WITH SKYWINGS") ── -->
    <section class="section-unleash" id="tentang-tte">
        <div class="unleash-card">
            <!-- Left Image with Floating Metric Badge -->
            <div class="unleash-image-wrap">
                <img src="{{ asset('images/banner/kantor-dkis-kota-cirebon.jpg') }}" alt="Gedung Kantor DKIS Kota Cirebon">
                <div class="unleash-badge-floating">
                    <span class="badge-val">{{ $macroStats['target_persentase'] }}%</span>
                    <span class="badge-lbl">Target Kepatuhan SPBE Kota</span>
                </div>
            </div>

            <!-- Right Giant Typography & Description -->
            <div class="unleash-content">
                <span class="eyebrow-tag">AKSELERASI BIROKRASI &bull; DKIS KOTA CIREBON</span>
                <h2>WUJUDKAN BIROKRASI MODERN DENGAN TTE TERVERIFIKASI</h2>
                <p>
                    Implementasi Tanda Tangan Elektronik tersertifikasi BSrE BSSN menjamin keaslian identitas penandatangan, keutuhan naskah dinas, dan nir-penolakan (<em>non-repudiation</em>) sesuai mandat UU ITE dan Perpres No. 95/2018 tentang Sistem Pemerintahan Berbasis Elektronik.
                </p>

                <!-- Mini Stat Cluster -->
                <div class="unleash-stats-row">
                    <div>
                        <div class="stat-mini-val">{{ number_format($macroStats['total_pendaftaran']) }}</div>
                        <div class="stat-mini-lbl">Permohonan Masuk</div>
                    </div>
                    <div>
                        <div class="stat-mini-val" style="color: #2563eb;">{{ number_format($macroStats['total_disetujui']) }}</div>
                        <div class="stat-mini-lbl">Telah Diverifikasi</div>
                    </div>
                    <div>
                        <div class="stat-mini-val" style="color: #10b981;">{{ number_format($macroStats['total_skpd']) }}</div>
                        <div class="stat-mini-lbl">SKPD Terkoneksi</div>
                    </div>
                </div>

                <a href="{{ route('pendaftaran.index') }}" class="btn-pill-large">
                    <span>Mulai Pendaftaran Sekarang</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ── 6. INTERACTIVE SKPD ADOPTION EXPLORER ──────────────────── -->
    <section class="section-explorer" id="skpd-explorer">
        <div class="explorer-card">
            <div class="explorer-top">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--slate-title); margin: 0 0 0.25rem 0;">
                        Transparansi &amp; Peringkat Capaian Adopsi SKPD
                    </h2>
                    <p style="font-size: 0.8125rem; color: var(--slate-text); margin: 0;">
                        Pemantauan real-time kepatuhan sertifikasi elektronik aparatur di seluruh unit kerja Pemerintah Kota Cirebon.
                    </p>
                </div>

                <div style="position: relative;">
                    <i class="ri-search-line" style="position: absolute; left: 14px; top: 12px; color: #94a3b8; font-size: 1.1rem;"></i>
                    <input type="text" 
                           id="skpd-search-input" 
                           class="explorer-search-input" 
                           placeholder="Cari nama dinas / perangkat daerah..." 
                           oninput="filterSkpdTable()">
                </div>
            </div>

            <!-- Filter Category Buttons -->
            <div class="filter-pills-row">
                <button type="button" class="filter-btn active" onclick="setCategoryFilter('ALL', this)">
                    Semua SKPD ({{ count($allSkpdList) }})
                </button>
                <button type="button" class="filter-btn" onclick="setCategoryFilter('OPTIMAL', this)">
                    Optimal &ge; 80% ({{ $macroStats['count_optimal'] ?? 0 }})
                </button>
                <button type="button" class="filter-btn" onclick="setCategoryFilter('PROGRESIF', this)">
                    Progresif 50-79% ({{ $macroStats['count_progresif'] ?? 0 }})
                </button>
                <button type="button" class="filter-btn" onclick="setCategoryFilter('AKSELERASI', this)">
                    Perlu Akselerasi &lt; 50% ({{ $macroStats['count_akselerasi'] ?? 0 }})
                </button>
            </div>

            <!-- Interactive Table -->
            <div style="overflow-x: auto;">
                <table class="table-modern" id="table-skpd-data">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Peringkat</th>
                            <th>Satuan Kerja Perangkat Daerah (SKPD)</th>
                            <th style="text-align: right;">Total Pegawai</th>
                            <th style="text-align: right;">TTE Terbit</th>
                            <th style="text-align: right;">Persentase</th>
                            <th style="width: 160px; text-align: center;">Progres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allSkpdList as $index => $skpd)
                            @php
                                $val = (float) $skpd->persentase_capaian;
                                $category = $val >= 80 ? 'OPTIMAL' : ($val >= 50 ? 'PROGRESIF' : 'AKSELERASI');
                                $color = $val >= 80 ? '#2563eb' : ($val >= 50 ? '#f59e0b' : '#ef4444');
                            @endphp
                            <tr class="skpd-row" 
                                data-name="{{ strtolower($skpd->nama_skpd) }}" 
                                data-category="{{ $category }}" 
                                data-percentage="{{ $val }}">
                                <td style="font-weight: 800; color: #64748b; text-align: center;">
                                    #{{ $index + 1 }}
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--slate-title);">{{ $skpd->nama_skpd }}</div>
                                    <div style="font-size: 0.6875rem; color: #94a3b8; font-family: monospace;">{{ $skpd->kode_skpd }}</div>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: #475569;">
                                    {{ number_format($skpd->total_asn) }}
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--sky-blue);">
                                    {{ number_format($skpd->total_disetujui) }}
                                </td>
                                <td style="text-align: right; font-weight: 800; color: {{ $color }};">
                                    {{ number_format($val, 1) }}%
                                </td>
                                <td style="text-align: center;">
                                    <div class="progress-bar-modern" style="margin: 0 auto;">
                                        <div class="progress-fill-modern" style="width: {{ min($val, 100) }}%; background: {{ $color }};"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: #94a3b8; padding: 2.5rem;">
                                    Belum ada data capaian SKPD yang tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
@endsection

{{-- Clean Minimalist Modern Footer --}}
@section('custom_footer')
<footer class="footer-modern">
    <div class="footer-inner">
        <div class="footer-col">
            <div class="brand-group" style="margin-bottom: 1rem;">
                <img src="{{ asset('images/branding/logo-dkis-kota-cirebon.jpg') }}" 
                     alt="Logo DKIS Kota Cirebon" 
                     style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div>
                    <div class="brand-text">SISMON TTE</div>
                    <div class="brand-sub">DKIS KOTA CIREBON</div>
                </div>
            </div>
            <p style="font-size: 0.8125rem; color: var(--slate-text); line-height: 1.6; margin: 0 0 1.25rem 0; max-width: 320px;">
                Sistem Monitoring Penerbitan Tanda Tangan Elektronik &amp; Pelayanan Email Dinas Resmi. Dikelola oleh Bidang Persandian Dinas Komunikasi, Informatika dan Statistik (DKIS) Kota Cirebon.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <span class="rating-pill" style="background: #f1f5f9; color: #475569; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <img src="{{ asset('images/branding/bsre-balai-besar-sertikasi-elektronik-2024-seeklogo.png') }}" alt="BSrE" style="height: 18px; object-fit: contain;"> Terverifikasi BSrE BSSN
                </span>
                <span class="rating-pill" style="background: #f1f5f9; color: #475569;">
                    <i class="ri-lock-line"></i> SSL/TLS 256-bit
                </span>
            </div>
        </div>

        <div class="footer-col">
            <h4>Layanan Publik</h4>
            <ul>
                <li><a href="{{ route('pendaftaran.index', ['tipe' => 'ASN']) }}">Pendaftaran Pegawai ASN</a></li>
                <li><a href="{{ route('pendaftaran.index', ['tipe' => 'NON_ASN']) }}">Pendaftaran Non-ASN</a></li>
                <li><a href="{{ route('pendaftaran.index') }}">Permohonan Email Dinas</a></li>
                <li><a href="#skpd-explorer">Cek Capaian SKPD</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Dasar Regulasi</h4>
            <ul>
                <li><a href="javascript:void(0)">UU No. 1/2024 (Revisi UU ITE)</a></li>
                <li><a href="javascript:void(0)">PP No. 71/2019 (PSTE)</a></li>
                <li><a href="javascript:void(0)">Perpres No. 95/2018 (SPBE)</a></li>
                <li><a href="javascript:void(0)">Perwali Penyelenggaraan TTE</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Kontak Bantuan</h4>
            <ul>
                <li><a href="javascript:void(0)"><i class="ri-map-pin-line"></i> Jl. Sudarsono No. 40, Cirebon</a></li>
                <li><a href="javascript:void(0)"><i class="ri-mail-line"></i> dkis@cirebonkota.go.id</a></li>
                <li><a href="javascript:void(0)"><i class="ri-time-line"></i> Senin - Jumat (08.00 - 16.00 WIB)</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div>&copy; {{ date('Y') }} Dinas Komunikasi, Informatika dan Statistik Kota Cirebon. Hak Cipta Dilindungi.</div>
        <div style="display: flex; gap: 1.5rem;">
            <span>Aman</span> &bull; <span>Akuntabel</span> &bull; <span>Bebas Kertas</span>
        </div>
    </div>
</footer>

<script>
    let activeCategory = 'ALL';

    function setCategoryFilter(category, btnEl) {
        activeCategory = category;
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
        filterSkpdTable();
    }

    function filterSkpdTable() {
        const query = (document.getElementById('skpd-search-input')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('.skpd-row');

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const category = row.getAttribute('data-category') || '';

            const matchQuery = !query || name.includes(query);
            const matchCategory = activeCategory === 'ALL' || category === activeCategory;

            row.style.display = (matchQuery && matchCategory) ? '' : 'none';
        });
    }

    function scrollDestinations(direction) {
        const container = document.getElementById('destination-slider');
        if (container) {
            container.scrollBy({ left: direction * 320, behavior: 'smooth' });
        }
    }
</script>
@endsection
