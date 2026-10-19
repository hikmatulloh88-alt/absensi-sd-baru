const sidebar=document.getElementById('sidebar');
document.getElementById('menuToggle')?.addEventListener('click',()=>sidebar?.classList.add('open'));
document.getElementById('closeMenu')?.addEventListener('click',()=>sidebar?.classList.remove('open'));
document.addEventListener('click',e=>{if(window.innerWidth<=700 && sidebar?.classList.contains('open') && !sidebar.contains(e.target) && !e.target.closest('#menuToggle')) sidebar.classList.remove('open')});

document.querySelectorAll('.nav-group-toggle').forEach(button=>{
  button.addEventListener('click',()=>{
    const expanded=button.getAttribute('aria-expanded')==='true';
    const targetId=button.getAttribute('aria-controls');
    const submenu=targetId ? document.getElementById(targetId) : null;
    button.setAttribute('aria-expanded',String(!expanded));
    submenu?.classList.toggle('expanded',!expanded);
  });
});
