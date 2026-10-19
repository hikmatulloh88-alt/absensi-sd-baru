const sidebar=document.getElementById('sidebar');
document.getElementById('menuToggle')?.addEventListener('click',()=>sidebar?.classList.add('open'));
document.getElementById('closeMenu')?.addEventListener('click',()=>sidebar?.classList.remove('open'));
document.addEventListener('click',e=>{if(window.innerWidth<=700 && sidebar?.classList.contains('open') && !sidebar.contains(e.target) && !e.target.closest('#menuToggle')) sidebar.classList.remove('open')});
