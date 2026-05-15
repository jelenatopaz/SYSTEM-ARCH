<?php // admin_nav_css.php — shared nav CSS, echo inside <style> ?>
nav { background:var(--purple-dark); padding:0 1.5rem; height:52px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 12px rgba(0,0,0,0.3); position:sticky; top:0; z-index:50; overflow-x:auto; }
.nav-brand { font-family:'Cinzel',serif; font-size:0.85rem; color:var(--gold); letter-spacing:0.04em; white-space:nowrap; margin-right:1rem; }
.nav-links { display:flex; align-items:center; gap:0.1rem; list-style:none; flex-wrap:nowrap; }
.nav-links a { color:rgba(255,255,255,0.85); text-decoration:none; font-size:0.78rem; padding:0.35rem 0.6rem; border-radius:5px; transition:background 0.2s,color 0.2s; white-space:nowrap; }
.nav-links a:hover,.nav-links a.active { background:rgba(240,165,0,0.15); color:var(--gold-light); }
.btn-logout { background:linear-gradient(135deg,var(--gold),var(--gold-light)); color:var(--text-dark); font-weight:700; border:none; padding:0.35rem 1rem; border-radius:6px; font-size:0.78rem; cursor:pointer; font-family:'Lato',sans-serif; margin-left:0.4rem; box-shadow:0 2px 8px rgba(240,165,0,0.3); white-space:nowrap; }