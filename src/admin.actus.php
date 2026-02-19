<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<?php 
	// SÉCURITÉ: Cette page est réservée aux administrateurs et registraires
	requireLevel(ROLE_REGISTRAR, './accueil');
	?>
	<title>Gestion des Actualités</title>
	<style>
		/* ========== SHADCN UI STYLE — ACTUS ADMIN ========== */
		:root {
			--background: 222.2 84% 4.9%;
			--foreground: 210 40% 98%;
			--card: 222.2 84% 4.9%;
			--card-foreground: 210 40% 98%;
			--primary: 199 89% 48%;
			--primary-foreground: 222.2 47.4% 11.2%;
			--secondary: 217.2 32.6% 17.5%;
			--secondary-foreground: 210 40% 98%;
			--muted: 217.2 32.6% 17.5%;
			--muted-foreground: 215 20.2% 65.1%;
			--destructive: 0 62.8% 30.6%;
			--destructive-foreground: 210 40% 98%;
			--border: 217.2 32.6% 17.5%;
			--input: 217.2 32.6% 17.5%;
			--ring: 199 89% 48%;
			--radius: 0.5rem;
		}

		/* Card Component */
		.shad-card {
			background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
			border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 0.75rem;
			backdrop-filter: blur(10px);
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
		}

		.shad-card-header { padding: 1.5rem 1.5rem 0; }
		.shad-card-title { font-size: 1.25rem; font-weight: 600; color: #f1f5f9; letter-spacing: -0.025em; }
		.shad-card-description { color: #94a3b8; font-size: 0.875rem; margin-top: 0.25rem; }
		.shad-card-content { padding: 1.5rem; }

		/* Button Component */
		.shad-btn {
			display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
			padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500;
			border-radius: 0.375rem; transition: all 0.2s ease; cursor: pointer; border: none; outline: none;
		}
		.shad-btn:focus-visible { outline: 2px solid #0ea5e9; outline-offset: 2px; }
		.shad-btn-primary { background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; box-shadow: 0 1px 3px rgba(14, 165, 233, 0.3); }
		.shad-btn-primary:hover { background: linear-gradient(135deg, #0284c7, #0369a1); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4); }
		.shad-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
		.shad-btn-secondary { background: rgba(51, 65, 85, 0.8); color: #e2e8f0; border: 1px solid rgba(71, 85, 105, 0.5); }
		.shad-btn-secondary:hover { background: rgba(71, 85, 105, 0.8); }
		.shad-btn-ghost { background: transparent; color: #94a3b8; }
		.shad-btn-ghost:hover { background: rgba(51, 65, 85, 0.5); color: #f1f5f9; }
		.shad-btn-icon { width: 2.25rem; height: 2.25rem; padding: 0; }
		.shad-btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
		.shad-btn-danger:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); transform: translateY(-1px); }
		.shad-btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
		.shad-btn-warning:hover { background: linear-gradient(135deg, #d97706, #b45309); transform: translateY(-1px); }
		.shad-btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
		.shad-btn-success:hover { background: linear-gradient(135deg, #16a34a, #15803d); transform: translateY(-1px); }
		.shad-btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

		/* Input Component */
		.shad-input {
			width: 100%; padding: 0.5rem 0.75rem !important; font-size: 0.875rem !important;
			background: rgba(30, 41, 59, 0.95) !important; border: 1px solid rgba(71, 85, 105, 0.8) !important;
			border-radius: 0.375rem; color: #ffffff !important; -webkit-text-fill-color: #ffffff !important;
			transition: all 0.2s ease; opacity: 1;
		}
		.shad-input:focus { outline: none; border-color: #0ea5e9 !important; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
		.shad-input::placeholder { color: #94a3b8 !important; -webkit-text-fill-color: #94a3b8 !important; opacity: 1; }

		/* Textarea Component */
		.shad-textarea {
			width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem;
			background: rgba(30, 41, 59, 0.95); border: 1px solid rgba(71, 85, 105, 0.8);
			border-radius: 0.375rem; color: #ffffff; transition: all 0.2s ease;
			resize: vertical; min-height: 100px; font-family: inherit;
		}
		.shad-textarea:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
		.shad-textarea::placeholder { color: #94a3b8; }

		/* Label Component */
		.shad-label { display: block; font-size: 0.875rem; font-weight: 500; color: #e2e8f0; margin-bottom: 0.375rem; }

		/* Select Component */
		.shad-select {
			width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem;
			background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(51, 65, 85, 0.8);
			border-radius: 0.375rem; color: #f1f5f9; cursor: pointer; transition: all 0.2s ease;
		}
		.shad-select:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
		.shad-select option { background: #1e293b; color: #f1f5f9; }

		/* Badge Component */
		.shad-badge {
			display: inline-flex; align-items: center; padding: 0.125rem 0.625rem;
			font-size: 0.75rem; font-weight: 500; border-radius: 9999px; transition: all 0.2s ease;
		}
		.shad-badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
		.shad-badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
		.shad-badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
		.shad-badge-info { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.3); }

		/* Category Badges */
		.cat-info { background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); }
		.cat-event { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
		.cat-academic { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
		.cat-urgent { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
		.cat-sport { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
		.cat-culture { background: rgba(236, 72, 153, 0.15); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.3); }

		/* ===== List Layout ===== */
		.annonce-list { list-style: none; padding: 0; margin: 0; }
		.annonce-item {
			display: flex; align-items: center; gap: 1rem;
			padding: 0.875rem 1.25rem;
			border-bottom: 1px solid rgba(51, 65, 85, 0.3);
			transition: background 0.15s ease;
		}
		.annonce-item:hover { background: rgba(51, 65, 85, 0.25); }
		.annonce-item:last-child { border-bottom: none; }
		.annonce-main { flex: 1; min-width: 0; }
		.annonce-title-row { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; }
		.annonce-title {
			font-size: 0.875rem; font-weight: 600; color: #f1f5f9;
			white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
		}
		.annonce-meta {
			display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
			font-size: 0.75rem; color: #64748b;
		}
		.annonce-meta i { font-size: 0.6875rem; }
		.annonce-meta-item { display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap; }
		.annonce-status { flex-shrink: 0; }
		.annonce-actions { display: flex; gap: 0.25rem; flex-shrink: 0; }

		/* Dialog/Modal Component */
		.shad-dialog-overlay {
			position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7);
			backdrop-filter: blur(4px); z-index: 50; display: none; animation: fadeIn 0.2s ease;
		}
		.shad-dialog-overlay.active { display: flex; align-items: center; justify-content: center; }
		@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
		@keyframes slideIn { from { opacity: 0; transform: scale(0.95) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

		.shad-dialog {
			background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 0.75rem; width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: slideIn 0.3s ease;
		}
		.shad-dialog-header {
			display: flex; align-items: center; justify-content: space-between;
			padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(51, 65, 85, 0.5);
		}
		.shad-dialog-title { font-size: 1.125rem; font-weight: 600; color: #f1f5f9; }
		.shad-dialog-body { padding: 1.5rem; }
		.shad-dialog-footer {
			display: flex; justify-content: flex-end; gap: 0.75rem;
			padding: 1rem 1.5rem; border-top: 1px solid rgba(51, 65, 85, 0.5); background: rgba(15, 23, 42, 0.5);
		}

		/* Switch/Toggle Component */
		.shad-switch { position: relative; display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer; }
		.shad-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
		.shad-switch-track {
			width: 2.75rem; height: 1.5rem; background: rgba(51, 65, 85, 0.8);
			border-radius: 9999px; transition: all 0.2s ease; position: relative;
		}
		.shad-switch-thumb {
			position: absolute; top: 2px; left: 2px; width: 1.25rem; height: 1.25rem;
			background: white; border-radius: 9999px; transition: all 0.2s ease;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
		}
		.shad-switch input:checked + .shad-switch-track { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
		.shad-switch input:checked + .shad-switch-track .shad-switch-thumb { transform: translateX(1.25rem); }
		.shad-switch-label { font-size: 0.875rem; color: #e2e8f0; }

		/* Photo Upload */
		.photo-upload-container {
			position: relative; width: 100%; aspect-ratio: 16/9; border-radius: 0.75rem; overflow: hidden;
			background: rgba(30, 41, 59, 0.5); border: 2px dashed rgba(51, 65, 85, 0.8);
			transition: all 0.2s ease; cursor: pointer; display: block;
		}
		.photo-upload-container:hover { border-color: #0ea5e9; background: rgba(14, 165, 233, 0.1); }
		.photo-upload-container img { width: 100%; height: 100%; object-fit: cover; }
		.photo-upload-placeholder {
			position: absolute; inset: 0; display: flex; flex-direction: column;
			align-items: center; justify-content: center; color: #64748b;
		}
		.photo-upload-placeholder i { font-size: 2.5rem; margin-bottom: 0.5rem; }

		/* Toast Notifications */
		.toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; pointer-events: none; }
		.toast {
			display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem;
			background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid rgba(51, 65, 85, 0.5);
			border-radius: 0.5rem; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
			min-width: 320px; max-width: 420px; pointer-events: auto; animation: toastSlideIn 0.3s ease; position: relative;
		}
		@keyframes toastSlideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
		@keyframes toastSlideOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(100%); } }
		.toast.removing { animation: toastSlideOut 0.3s ease forwards; }
		.toast-icon { flex-shrink: 0; width: 1.5rem; height: 1.5rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; }
		.toast-success .toast-icon { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
		.toast-error .toast-icon { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
		.toast-warning .toast-icon { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
		.toast-info .toast-icon { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; }
		.toast-content { flex: 1; }
		.toast-title { font-size: 0.875rem; font-weight: 600; color: #f1f5f9; margin-bottom: 0.25rem; }
		.toast-message { font-size: 0.8125rem; color: #94a3b8; }
		.toast-close { flex-shrink: 0; background: none; border: none; color: #64748b; cursor: pointer; padding: 0.25rem; transition: color 0.2s ease; }
		.toast-close:hover { color: #f1f5f9; }
		.toast-progress { position: absolute; bottom: 0; left: 0; height: 3px; border-radius: 0 0 0.5rem 0.5rem; animation: toastProgress 5s linear forwards; }
		.toast-success .toast-progress { background: #22c55e; }
		.toast-error .toast-progress { background: #ef4444; }
		.toast-warning .toast-progress { background: #f59e0b; }
		.toast-info .toast-progress { background: #0ea5e9; }
		@keyframes toastProgress { from { width: 100%; } to { width: 0%; } }

		/* Separator */
		.shad-separator { height: 1px; background: linear-gradient(to right, transparent, rgba(51, 65, 85, 0.5), transparent); margin: 1rem 0; }

		/* Form Group */
		.form-group { margin-bottom: 1rem; }

		/* Header Actions */
		.header-actions {
			display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem 1.5rem;
			background: rgba(15, 23, 42, 0.5); border-bottom: 1px solid rgba(51, 65, 85, 0.3);
		}
		.header-row {
			display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
		}

		/* Search Input */
		.search-wrapper { position: relative; flex: 1; max-width: 350px; min-width: 180px; }
		.search-wrapper i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #64748b; }
		.search-wrapper .shad-input { padding-left: 2.5rem !important; }

		/* Filter Pills */
		.filter-pills { display: flex; gap: 0.375rem; flex-wrap: wrap; align-items: center; }
		.filter-pill {
			padding: 0.25rem 0.625rem; font-size: 0.75rem; border-radius: 9999px;
			background: rgba(51, 65, 85, 0.5); color: #94a3b8; border: 1px solid rgba(71, 85, 105, 0.5);
			cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
		}
		.filter-pill:hover { background: rgba(71, 85, 105, 0.5); color: #e2e8f0; }
		.filter-pill.active { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; border-color: rgba(14, 165, 233, 0.4); }

		/* List header */
		.list-header {
			display: flex; align-items: center; gap: 1rem;
			padding: 0.625rem 1.25rem;
			background: rgba(30, 41, 59, 0.6);
			border-bottom: 1px solid rgba(51, 65, 85, 0.5);
			font-size: 0.6875rem; font-weight: 600; color: #64748b;
			text-transform: uppercase; letter-spacing: 0.05em;
		}

		/* Stats Cards */
		.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
		.stat-card {
			background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(51, 65, 85, 0.3);
			border-radius: 0.5rem; padding: 1rem; display: flex; align-items: center; gap: 1rem;
		}
		.stat-icon {
			width: 3rem; height: 3rem; border-radius: 0.5rem;
			display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
		}
		.stat-icon.primary { background: rgba(14, 165, 233, 0.2); color: #0ea5e9; }
		.stat-icon.success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
		.stat-icon.warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
		.stat-icon.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
		.stat-icon.info { background: rgba(168, 85, 247, 0.2); color: #a855f7; }
		.stat-content h4 { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
		.stat-content p { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; }

		/* Pin indicator */
		.pin-dot { color: #f59e0b; flex-shrink: 0; font-size: 0.8125rem; }

		/* Status dot */
		.status-dot {
			width: 0.5rem; height: 0.5rem; border-radius: 50%; flex-shrink: 0;
		}
		.status-dot.active { background: #22c55e; box-shadow: 0 0 6px rgba(34, 197, 94, 0.4); }
		.status-dot.inactive { background: #ef4444; }
		.status-dot.expired { background: #f59e0b; }

		/* Confirm Dialog */
		.confirm-dialog {
			max-width: 420px;
		}
		.confirm-dialog .shad-dialog-body {
			text-align: center; padding: 2rem 1.5rem;
		}
		.confirm-dialog .confirm-icon {
			width: 4rem; height: 4rem; border-radius: 9999px; margin: 0 auto 1rem;
			display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
			background: rgba(239, 68, 68, 0.15); color: #ef4444;
		}
		.confirm-dialog .confirm-title { font-size: 1.125rem; font-weight: 600; color: #f1f5f9; margin-bottom: 0.5rem; }
		.confirm-dialog .confirm-message { color: #94a3b8; font-size: 0.875rem; }

		/* Space utility */
		.space-y-4 > * + * { margin-top: 1rem; }

		/* Responsive */
		@media (max-width: 1024px) {
			.search-wrapper { max-width: 100%; min-width: 0; flex: 1; }
			.filter-pills { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 0.25rem; -webkit-overflow-scrolling: touch; }
			.filter-pills::-webkit-scrollbar { height: 3px; }
			.filter-pills::-webkit-scrollbar-thumb { background: #1a3a5c; border-radius: 3px; }
		}
		@media (max-width: 768px) {
			.header-row { flex-direction: column; align-items: stretch; }
			.search-wrapper { max-width: 100%; }
			.shad-dialog { max-width: 95vw; margin: 0 0.5rem; }
			.stats-grid { grid-template-columns: repeat(2, 1fr); }
			.annonce-item { flex-wrap: wrap; padding: 0.75rem 1rem; }
			.annonce-actions { width: 100%; justify-content: flex-end; padding-top: 0.5rem; border-top: 1px solid rgba(51,65,85,0.2); margin-top: 0.5rem; }
		}

		/* Light mode overrides */
		[data-theme="light"] .shad-card { background: linear-gradient(145deg, rgba(241, 245, 249, 0.95), rgba(226, 232, 240, 0.95)); border-color: #cbd5e1; }
		[data-theme="light"] .shad-card-title { color: #0f172a; }
		[data-theme="light"] .annonce-item { border-bottom-color: #e2e8f0; }
		[data-theme="light"] .annonce-item:hover { background: rgba(241, 245, 249, 0.5); }
		[data-theme="light"] .annonce-title { color: #0f172a; }
		[data-theme="light"] .annonce-meta { color: #64748b; }
		[data-theme="light"] .list-header { background: rgba(241, 245, 249, 0.6); border-bottom-color: #cbd5e1; color: #475569; }
		[data-theme="light"] .shad-input { background: rgba(255,255,255,0.95) !important; border-color: #cbd5e1 !important; color: #0f172a !important; -webkit-text-fill-color: #0f172a !important; }
		[data-theme="light"] .shad-textarea { background: rgba(255,255,255,0.95); border-color: #cbd5e1; color: #0f172a; }
		[data-theme="light"] .shad-select { background: rgba(255,255,255,0.8); border-color: #cbd5e1; color: #0f172a; }
		[data-theme="light"] .shad-label { color: #334155; }
		[data-theme="light"] .shad-dialog { background: linear-gradient(145deg, #f8fafc, #e2e8f0); border-color: #cbd5e1; }
		[data-theme="light"] .shad-dialog-title { color: #0f172a; }
		[data-theme="light"] .shad-dialog-header, [data-theme="light"] .shad-dialog-footer { border-color: #cbd5e1; }
		[data-theme="light"] .shad-dialog-footer { background: rgba(241, 245, 249, 0.5); }
		[data-theme="light"] .header-actions { background: rgba(241, 245, 249, 0.5); border-bottom-color: #cbd5e1; }
		[data-theme="light"] .stat-card { background: rgba(255, 255, 255, 0.8); border-color: #e2e8f0; }
		[data-theme="light"] .stat-content h4 { color: #64748b; }
		[data-theme="light"] .stat-content p { color: #0f172a; }
		[data-theme="light"] .filter-pill { background: rgba(226, 232, 240, 0.8); color: #475569; border-color: #cbd5e1; }
		[data-theme="light"] .filter-pill:hover { background: rgba(203, 213, 225, 0.8); color: #0f172a; }
		[data-theme="light"] .filter-pill.active { background: rgba(14, 165, 233, 0.15); color: #0284c7; border-color: rgba(14, 165, 233, 0.3); }

		[data-theme="light"] .toast { background: linear-gradient(145deg, #f8fafc, #e2e8f0); border-color: #cbd5e1; }
		[data-theme="light"] .toast-title { color: #0f172a; }
		[data-theme="light"] .toast-message { color: #64748b; }
		[data-theme="light"] .confirm-dialog .confirm-title { color: #0f172a; }
		[data-theme="light"] .confirm-dialog .confirm-message { color: #64748b; }
	</style>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">
		
		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

		<!-- Toast Container -->
		<div class="toast-container" id="toastContainer"></div>

		<div class="w-full flex flex-col lg:flex-row">
			
			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
				<div class="back p-4 flex-1 overflow-y-auto">
					
					<?php
					// ============================================================
					// Données
					// ============================================================
					$categories = [
						'info'     => ['label' => 'Information',  'icon' => 'bi-info-circle-fill',      'class' => 'cat-info'],
						'event'    => ['label' => 'Événement',    'icon' => 'bi-calendar-event-fill',   'class' => 'cat-event'],
						'academic' => ['label' => 'Académique',   'icon' => 'bi-mortarboard-fill',      'class' => 'cat-academic'],
						'urgent'   => ['label' => 'Urgent',       'icon' => 'bi-exclamation-triangle-fill', 'class' => 'cat-urgent'],
						'sport'    => ['label' => 'Sport',        'icon' => 'bi-trophy-fill',           'class' => 'cat-sport'],
						'culture'  => ['label' => 'Culture',      'icon' => 'bi-palette-fill',          'class' => 'cat-culture'],
					];

					// Vérifier si la table existe
					$tableExists = false;
					try {
						$check = $dtb->query("SHOW TABLES LIKE 't_annonces'");
						$tableExists = $check->rowCount() > 0;
					} catch(Exception $e) {}

					$annonces = [];
					$stats = ['total' => 0, 'active' => 0, 'pinned' => 0, 'expired' => 0, 'categories' => []];

					if ($tableExists) {
						// Stats
						$stats['total'] = (int) $dtb->query("SELECT COUNT(*) FROM t_annonces")->fetchColumn();
						$stats['active'] = (int) $dtb->query("SELECT COUNT(*) FROM t_annonces WHERE is_active = 1")->fetchColumn();
						$stats['pinned'] = (int) $dtb->query("SELECT COUNT(*) FROM t_annonces WHERE is_pinned = 1")->fetchColumn();
						$stats['expired'] = (int) $dtb->query("SELECT COUNT(*) FROM t_annonces WHERE expire_date IS NOT NULL AND expire_date < CURDATE()")->fetchColumn();
						
						// Cat counts
						$catCounts = $dtb->query("SELECT category, COUNT(*) as cnt FROM t_annonces GROUP BY category");
						while ($row = $catCounts->fetch()) {
							$stats['categories'][$row['category']] = (int) $row['cnt'];
						}

						// All announcements
						$annonces = DB::select("SELECT * FROM t_annonces ORDER BY is_pinned DESC, publish_date DESC, created_at DESC");
					}
					?>

					<!-- Stats Cards -->
					<div class="stats-grid">
						<div class="stat-card">
							<div class="stat-icon primary"><i class="bi-megaphone-fill"></i></div>
							<div class="stat-content">
								<h4>Total Annonces</h4>
								<p><?= $stats['total'] ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon success"><i class="bi-eye-fill"></i></div>
							<div class="stat-content">
								<h4>Actives</h4>
								<p><?= $stats['active'] ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon warning"><i class="bi-pin-angle-fill"></i></div>
							<div class="stat-content">
								<h4>Épinglées</h4>
								<p><?= $stats['pinned'] ?></p>
							</div>
						</div>
						<div class="stat-card">
							<div class="stat-icon danger"><i class="bi-clock-history"></i></div>
							<div class="stat-content">
								<h4>Expirées</h4>
								<p><?= $stats['expired'] ?></p>
							</div>
						</div>
					</div>

					<!-- Main Card -->
					<div class="shad-card">
						<div class="header-actions">
							<div class="header-row">
								<div class="search-wrapper">
									<i class="bi-search"></i>
									<input type="text" id="searchAnnonces" class="shad-input" placeholder="Rechercher une annonce...">
								</div>
								<button id="addAnnonceBtn" class="shad-btn shad-btn-primary">
									<i class="bi-plus-lg"></i>
									Nouvelle Annonce
								</button>
							</div>
							<div class="filter-pills">
								<button class="filter-pill active" data-filter="all">Tout</button>
								<?php foreach ($categories as $key => $cat): ?>
									<button class="filter-pill" data-filter="<?= $key ?>">
										<i class="<?= $cat['icon'] ?> mr-1"></i><?= $cat['label'] ?>
										<?php if (!empty($stats['categories'][$key])): ?>
											<span class="ml-1 opacity-60">(<?= $stats['categories'][$key] ?>)</span>
										<?php endif; ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="shad-card-content" style="padding: 0;">
							<?php if (!$tableExists): ?>
								<div class="text-center py-12">
									<i class="bi-database-x text-4xl text-slate-500 mb-3 block"></i>
									<p class="text-slate-400 mb-4">La table <code class="text-cyan-400">t_annonces</code> n'existe pas encore.</p>
									<p class="text-slate-500 text-sm">Exécutez le fichier <code class="text-cyan-400">data/sql_annonces.sql</code> pour créer la table.</p>
								</div>
							<?php elseif (empty($annonces)): ?>
								<div class="text-center py-12">
									<i class="bi-megaphone text-4xl text-slate-500 mb-3 block"></i>
									<p class="text-slate-400 mb-2">Aucune annonce pour le moment.</p>
									<p class="text-slate-500 text-sm">Cliquez sur « Nouvelle Annonce » pour en créer une.</p>
								</div>
							<?php else: ?>
								<ul class="annonce-list" id="annoncesTable">
<?php foreach ($annonces as $annonce): 
	$cat = $categories[$annonce['category']] ?? $categories['info'];
	$isExpired = !empty($annonce['expire_date']) && $annonce['expire_date'] < date('Y-m-d');
?>
									<li class="annonce-item"
										data-id="<?= $annonce['id'] ?>"
										data-category="<?= e($annonce['category']) ?>"
										data-search="<?= strtolower(e($annonce['title'] . ' ' . $annonce['excerpt'] . ' ' . $annonce['author'] . ' ' . $annonce['category'])) ?>">

										<!-- Status dot -->
										<div class="annonce-status" title="<?= !$annonce['is_active'] ? 'Inactive' : ($isExpired ? 'Expirée' : 'Active') ?>">
											<span class="status-dot <?= !$annonce['is_active'] ? 'inactive' : ($isExpired ? 'expired' : 'active') ?>"></span>
										</div>

										<!-- Main content -->
										<div class="annonce-main">
											<div class="annonce-title-row">
												<?php if ($annonce['is_pinned']): ?>
													<i class="bi-pin-angle-fill pin-dot" title="Épinglée"></i>
												<?php endif; ?>
												<span class="annonce-title"><?= e($annonce['title']) ?></span>
												<span class="shad-badge <?= $cat['class'] ?>" style="flex-shrink:0;">
													<i class="<?= $cat['icon'] ?> mr-1"></i><?= $cat['label'] ?>
												</span>
											</div>
											<div class="annonce-meta">
												<span class="annonce-meta-item"><i class="bi-person"></i> <?= e($annonce['author'] ?: '—') ?></span>
												<span class="annonce-meta-item"><i class="bi-calendar3"></i> <?= $annonce['publish_date'] ? date('d/m/Y', strtotime($annonce['publish_date'])) : '—' ?><?php if (!empty($annonce['expire_date'])): ?> <span class="<?= $isExpired ? 'text-red-400' : '' ?>">→ <?= date('d/m/Y', strtotime($annonce['expire_date'])) ?></span><?php endif; ?></span>
												<?php if ($annonce['excerpt']): ?>
													<span class="annonce-meta-item" style="color:#94a3b8;">— <?= e(mb_substr($annonce['excerpt'], 0, 60)) ?><?= mb_strlen($annonce['excerpt']) > 60 ? '…' : '' ?></span>
												<?php endif; ?>
											</div>
										</div>

										<!-- Actions -->
										<div class="annonce-actions">
											<button class="shad-btn shad-btn-ghost shad-btn-icon editAnnonceBtn" data-id="<?= $annonce['id'] ?>" title="Modifier">
												<i class="bi-pencil"></i>
											</button>
											<button class="shad-btn shad-btn-ghost shad-btn-icon togglePinBtn" data-id="<?= $annonce['id'] ?>" data-pinned="<?= $annonce['is_pinned'] ?>" title="<?= $annonce['is_pinned'] ? 'Désépingler' : 'Épingler' ?>">
												<i class="bi-pin-angle<?= $annonce['is_pinned'] ? '-fill text-yellow-400' : '' ?>"></i>
											</button>
											<button class="shad-btn shad-btn-ghost shad-btn-icon toggleActiveBtn" data-id="<?= $annonce['id'] ?>" data-active="<?= $annonce['is_active'] ?>" title="<?= $annonce['is_active'] ? 'Masquer' : 'Activer' ?>">
												<i class="bi-eye<?= $annonce['is_active'] ? '-fill text-green-400' : '-slash text-slate-500' ?>"></i>
											</button>
											<button class="shad-btn shad-btn-ghost shad-btn-icon deleteAnnonceBtn" data-id="<?= $annonce['id'] ?>" data-title="<?= e($annonce['title']) ?>" title="Supprimer">
												<i class="bi-trash text-red-400"></i>
											</button>
										</div>
									</li>
<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>

				</div>

				<!-- FOOTER -->
				<?php require('../init/footer.php'); ?>

			</div>

		</div>

	</div>

<!-- ============================================================ -->
<!-- MODAL: Créer une annonce -->
<!-- ============================================================ -->
<div class="shad-dialog-overlay" id="addAnnonceDialog">
	<div class="shad-dialog">
		<form id="formAddAnnonce" method="post" action="<?=$app_base?>/app/.annonces/add.annonce" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="shad-dialog-header">
				<h3 class="shad-dialog-title"><i class="bi-plus-circle-fill mr-2 text-cyan-400"></i>Nouvelle Annonce</h3>
				<button type="button" class="shad-btn shad-btn-ghost shad-btn-icon closeDialogBtn"><i class="bi-x-lg"></i></button>
			</div>
			
			<div class="shad-dialog-body space-y-4">
				<div class="form-group">
					<label class="shad-label">Titre <span class="text-red-400">*</span></label>
					<input class="shad-input" type="text" name="title" required maxlength="255" placeholder="Titre de l'annonce...">
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="form-group">
						<label class="shad-label">Catégorie <span class="text-red-400">*</span></label>
						<select class="shad-select" name="category" required>
							<?php foreach ($categories as $key => $cat): ?>
								<option value="<?= $key ?>"><?= $cat['label'] ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label class="shad-label">Auteur</label>
						<input class="shad-input" type="text" name="author" value="<?= e($rg_name . ' ' . $rg_last_name) ?>" placeholder="Auteur...">
					</div>
				</div>

				<div class="form-group">
					<label class="shad-label">Résumé (aperçu)</label>
					<input class="shad-input" type="text" name="excerpt" maxlength="500" placeholder="Court résumé pour l'aperçu dans la page Actus...">
				</div>

				<div class="form-group">
					<label class="shad-label">Contenu complet <span class="text-red-400">*</span></label>
					<textarea class="shad-textarea" name="content" required rows="6" placeholder="Contenu détaillé de l'annonce..."></textarea>
				</div>

				<div class="form-group">
					<label class="shad-label">Image d'illustration</label>
					<label class="photo-upload-container" for="imageAdd">
						<img src="" id="imageAddPreview" style="display: none;">
						<div class="photo-upload-placeholder" id="imageAddPlaceholder">
							<i class="bi-image"></i>
							<span class="text-sm">Choisir une image (optionnel)</span>
						</div>
					</label>
					<input type="file" accept="image/*" name="image" id="imageAdd" class="hidden">
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="form-group">
						<label class="shad-label">Date de publication</label>
						<input class="shad-input" type="date" name="publish_date" value="<?= date('Y-m-d') ?>">
					</div>
					<div class="form-group">
						<label class="shad-label">Date d'expiration</label>
						<input class="shad-input" type="date" name="expire_date" placeholder="Laisser vide = pas d'expiration">
					</div>
				</div>

				<div class="flex gap-6">
					<label class="shad-switch">
						<input type="checkbox" name="is_pinned" value="1">
						<div class="shad-switch-track"><div class="shad-switch-thumb"></div></div>
						<span class="shad-switch-label"><i class="bi-pin-angle mr-1"></i>Épingler</span>
					</label>
					<label class="shad-switch">
						<input type="checkbox" name="is_active" value="1" checked>
						<div class="shad-switch-track"><div class="shad-switch-thumb"></div></div>
						<span class="shad-switch-label"><i class="bi-eye mr-1"></i>Active</span>
					</label>
				</div>
			</div>
			
			<div class="shad-dialog-footer">
				<button type="button" class="shad-btn shad-btn-secondary closeDialogBtn">Annuler</button>
				<button type="submit" class="shad-btn shad-btn-primary"><i class="bi-check-lg"></i> Publier</button>
			</div>
		</form>
	</div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Modifier une annonce -->
<!-- ============================================================ -->
<div class="shad-dialog-overlay" id="editAnnonceDialog">
	<div class="shad-dialog">
		<form id="formEditAnnonce" method="post" action="<?=$app_base?>/app/.annonces/update.annonce" enctype="multipart/form-data">
			<?= csrf_field() ?>
			<input type="hidden" name="id" id="editId">
			<div class="shad-dialog-header">
				<h3 class="shad-dialog-title"><i class="bi-pencil-square mr-2 text-cyan-400"></i>Modifier l'Annonce</h3>
				<button type="button" class="shad-btn shad-btn-ghost shad-btn-icon closeDialogBtn"><i class="bi-x-lg"></i></button>
			</div>
			
			<div class="shad-dialog-body space-y-4">
				<div class="form-group">
					<label class="shad-label">Titre <span class="text-red-400">*</span></label>
					<input class="shad-input" type="text" name="title" id="editTitle" required maxlength="255">
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="form-group">
						<label class="shad-label">Catégorie <span class="text-red-400">*</span></label>
						<select class="shad-select" name="category" id="editCategory" required>
							<?php foreach ($categories as $key => $cat): ?>
								<option value="<?= $key ?>"><?= $cat['label'] ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label class="shad-label">Auteur</label>
						<input class="shad-input" type="text" name="author" id="editAuthor">
					</div>
				</div>

				<div class="form-group">
					<label class="shad-label">Résumé (aperçu)</label>
					<input class="shad-input" type="text" name="excerpt" id="editExcerpt" maxlength="500">
				</div>

				<div class="form-group">
					<label class="shad-label">Contenu complet <span class="text-red-400">*</span></label>
					<textarea class="shad-textarea" name="content" id="editContent" required rows="6"></textarea>
				</div>

				<div class="form-group">
					<label class="shad-label">Image d'illustration</label>
					<label class="photo-upload-container" for="imageEdit">
						<img src="" id="imageEditPreview" style="display: none;">
						<div class="photo-upload-placeholder" id="imageEditPlaceholder">
							<i class="bi-image"></i>
							<span class="text-sm">Changer l'image</span>
						</div>
					</label>
					<input type="file" accept="image/*" name="image" id="imageEdit" class="hidden">
					<input type="hidden" name="oldImage" id="editOldImage">
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div class="form-group">
						<label class="shad-label">Date de publication</label>
						<input class="shad-input" type="date" name="publish_date" id="editPublishDate">
					</div>
					<div class="form-group">
						<label class="shad-label">Date d'expiration</label>
						<input class="shad-input" type="date" name="expire_date" id="editExpireDate">
					</div>
				</div>

				<div class="flex gap-6">
					<label class="shad-switch">
						<input type="checkbox" name="is_pinned" id="editPinned" value="1">
						<div class="shad-switch-track"><div class="shad-switch-thumb"></div></div>
						<span class="shad-switch-label"><i class="bi-pin-angle mr-1"></i>Épingler</span>
					</label>
					<label class="shad-switch">
						<input type="checkbox" name="is_active" id="editActive" value="1">
						<div class="shad-switch-track"><div class="shad-switch-thumb"></div></div>
						<span class="shad-switch-label"><i class="bi-eye mr-1"></i>Active</span>
					</label>
				</div>
			</div>
			
			<div class="shad-dialog-footer">
				<button type="button" class="shad-btn shad-btn-secondary closeDialogBtn">Annuler</button>
				<button type="submit" class="shad-btn shad-btn-primary"><i class="bi-check-lg"></i> Enregistrer</button>
			</div>
		</form>
	</div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Confirmation de suppression -->
<!-- ============================================================ -->
<div class="shad-dialog-overlay" id="deleteAnnonceDialog">
	<div class="shad-dialog confirm-dialog">
		<form id="formDeleteAnnonce" method="post" action="<?=$app_base?>/app/.annonces/delete.annonce">
			<?= csrf_field() ?>
			<input type="hidden" name="id" id="deleteId">
			<div class="shad-dialog-body">
				<div class="confirm-icon"><i class="bi-trash"></i></div>
				<div class="confirm-title">Supprimer cette annonce ?</div>
				<div class="confirm-message" id="deleteMessage">Cette action est irréversible.</div>
			</div>
			<div class="shad-dialog-footer" style="justify-content: center;">
				<button type="button" class="shad-btn shad-btn-secondary closeDialogBtn">Annuler</button>
				<button type="submit" class="shad-btn shad-btn-danger"><i class="bi-trash"></i> Supprimer</button>
			</div>
		</form>
	</div>
</div>

<script>
$(document).ready(function() {
	
	// ============================================================
	// Toast
	// ============================================================
	window.showToast = function(type, title, message, duration = 5000) {
		const icons = {
			success: 'bi-check-circle-fill', error: 'bi-x-circle-fill',
			warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill'
		};
		const toast = $(`
			<div class="toast toast-${type}">
				<div class="toast-icon"><i class="${icons[type]}"></i></div>
				<div class="toast-content">
					<div class="toast-title">${title}</div>
					<div class="toast-message">${message}</div>
				</div>
				<button class="toast-close"><i class="bi-x"></i></button>
				<div class="toast-progress"></div>
			</div>
		`);
		$('#toastContainer').append(toast);
		const timeout = setTimeout(() => removeToast(toast), duration);
		toast.find('.toast-close').on('click', function() { clearTimeout(timeout); removeToast(toast); });
	};
	function removeToast(toast) { toast.addClass('removing'); setTimeout(() => toast.remove(), 300); }

	// ============================================================
	// Dialog Management
	// ============================================================
	$('#addAnnonceBtn').on('click', () => $('#addAnnonceDialog').addClass('active'));
	$('.closeDialogBtn').on('click', function() { $(this).closest('.shad-dialog-overlay').removeClass('active'); });
	$('.shad-dialog-overlay').on('click', function(e) { if (e.target === this) $(this).removeClass('active'); });
	$(document).on('keydown', function(e) { if (e.key === 'Escape') $('.shad-dialog-overlay.active').removeClass('active'); });

	// ============================================================
	// Search
	// ============================================================
	$('#searchAnnonces').on('input', function() {
		const search = $(this).val().toLowerCase();
		$('#annoncesTable .annonce-item').each(function() {
			const text = $(this).data('search') || '';
			$(this).toggle(text.includes(search));
		});
	});

	// ============================================================
	// Filter Pills
	// ============================================================
	$('.filter-pill').on('click', function() {
		$('.filter-pill').removeClass('active');
		$(this).addClass('active');
		const filter = $(this).data('filter');
		$('#annoncesTable .annonce-item').each(function() {
			if (filter === 'all') {
				$(this).show();
			} else {
				$(this).toggle($(this).data('category') === filter);
			}
		});
	});

	// ============================================================
	// Image Preview
	// ============================================================
	$('#imageAdd').on('change', function() {
		if (this.files && this.files[0]) {
			const reader = new FileReader();
			reader.onload = function(e) {
				$('#imageAddPreview').attr('src', e.target.result).show();
				$('#imageAddPlaceholder').hide();
			};
			reader.readAsDataURL(this.files[0]);
		}
	});

	$('#imageEdit').on('change', function() {
		if (this.files && this.files[0]) {
			const reader = new FileReader();
			reader.onload = function(e) {
				$('#imageEditPreview').attr('src', e.target.result).show();
				$('#imageEditPlaceholder').hide();
			};
			reader.readAsDataURL(this.files[0]);
		}
	});

	// ============================================================
	// Edit Annonce — load data via AJAX
	// ============================================================
	$('.editAnnonceBtn').on('click', function() {
		const id = $(this).data('id');
		
		// Fetch data
		$.get(APP_BASE+'/app/.annonces/get.annonce', { id: id }, function(data) {
			if (data.success) {
				const a = data.annonce;
				$('#editId').val(a.id);
				$('#editTitle').val(a.title);
				$('#editCategory').val(a.category);
				$('#editAuthor').val(a.author || '');
				$('#editExcerpt').val(a.excerpt || '');
				$('#editContent').val(a.content);
				$('#editPublishDate').val(a.publish_date || '');
				$('#editExpireDate').val(a.expire_date || '');
				$('#editPinned').prop('checked', a.is_pinned == 1);
				$('#editActive').prop('checked', a.is_active == 1);
				$('#editOldImage').val(a.image || '');
				
				// Image preview
				if (a.image) {
					$('#imageEditPreview').attr('src', '../app/uploads/annonces/' + a.image).show();
					$('#imageEditPlaceholder').hide();
				} else {
					$('#imageEditPreview').hide();
					$('#imageEditPlaceholder').show();
				}
				
				$('#editAnnonceDialog').addClass('active');
			} else {
				showToast('error', 'Erreur', data.message || 'Impossible de charger l\'annonce.');
			}
		}, 'json').fail(function() {
			showToast('error', 'Erreur', 'Erreur de communication avec le serveur.');
		});
	});

	// ============================================================
	// Toggle Pin
	// ============================================================
	$('.togglePinBtn').on('click', function() {
		const btn = $(this);
		const id = btn.data('id');
		const currentlyPinned = btn.data('pinned');
		
		$.post(APP_BASE+'/app/.annonces/toggle.annonce', { 
			id: id, 
			field: 'is_pinned', 
			value: currentlyPinned ? 0 : 1,
			csrf_token: '<?= csrf_token() ?>'
		}, function(data) {
			if (data.success) {
				showToast('success', 'Succès', data.message);
				setTimeout(() => location.reload(), 800);
			} else {
				showToast('error', 'Erreur', data.message || 'Erreur');
			}
		}, 'json').fail(function() {
			showToast('error', 'Erreur', 'Erreur serveur.');
		});
	});

	// ============================================================
	// Toggle Active
	// ============================================================
	$('.toggleActiveBtn').on('click', function() {
		const btn = $(this);
		const id = btn.data('id');
		const currentlyActive = btn.data('active');
		
		$.post(APP_BASE+'/app/.annonces/toggle.annonce', { 
			id: id, 
			field: 'is_active', 
			value: currentlyActive ? 0 : 1,
			csrf_token: '<?= csrf_token() ?>'
		}, function(data) {
			if (data.success) {
				showToast('success', 'Succès', data.message);
				setTimeout(() => location.reload(), 800);
			} else {
				showToast('error', 'Erreur', data.message || 'Erreur');
			}
		}, 'json').fail(function() {
			showToast('error', 'Erreur', 'Erreur serveur.');
		});
	});

	// ============================================================
	// Delete Annonce
	// ============================================================
	$('.deleteAnnonceBtn').on('click', function() {
		const id = $(this).data('id');
		const title = $(this).data('title');
		$('#deleteId').val(id);
		$('#deleteMessage').html('Voulez-vous vraiment supprimer l\'annonce<br><strong class="text-cyan-400">' + title + '</strong> ?');
		$('#deleteAnnonceDialog').addClass('active');
	});

	// ============================================================
	// Form Submissions
	// ============================================================
	$('#formAddAnnonce').on('submit', function() { showToast('info', 'Publication...', 'Création de l\'annonce en cours.'); });
	$('#formEditAnnonce').on('submit', function() { showToast('info', 'Enregistrement...', 'Mise à jour en cours.'); });
	$('#formDeleteAnnonce').on('submit', function() { showToast('info', 'Suppression...', 'Suppression en cours.'); });

	// ============================================================
	// URL Params for Toast
	// ============================================================
	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.get('success') === '1') {
		showToast('success', 'Succès !', urlParams.get('msg') || 'Opération effectuée avec succès.');
		window.history.replaceState({}, document.title, window.location.pathname);
	}
	if (urlParams.get('error')) {
		showToast('error', 'Erreur', decodeURIComponent(urlParams.get('error')));
		window.history.replaceState({}, document.title, window.location.pathname);
	}
});
</script>

</body>
</html>
