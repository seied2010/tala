(()=>{
    'use strict';
    const root=document.documentElement;
    const read=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(key)||JSON.stringify(fallback));}catch{return fallback;}};
    const write=(key,value)=>{try{if(value&&Object.keys(value).length)localStorage.setItem(key,JSON.stringify(value));else localStorage.removeItem(key);}catch{}};
    const fa=value=>String(value).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
    let prefs=read('gc-personalize',{});
    if(!['light','dark'].includes(prefs.theme)){try{prefs.theme=localStorage.getItem('gc-theme')==='dark'?'dark':'light';}catch{prefs.theme='light';}}
    let a11y=read('gc-a11y',[]);if(!Array.isArray(a11y))a11y=[];

    const applyPrefs=()=>{
        root.dataset.theme=prefs.theme==='dark'?'dark':'light';
        if(/^#[0-9A-F]{6}$/i.test(prefs.accent||''))root.style.setProperty('--accent',prefs.accent);else root.style.removeProperty('--accent');
        root.classList.toggle('gc-system-font',prefs.font==='system');
        const size=Number(prefs.size)||100;root.style.fontSize=size>=90&&size<=130&&size!==100?size+'%':'';
        document.querySelectorAll('[data-theme-value]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.themeValue===root.dataset.theme)));
        document.querySelectorAll('[data-font-value]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.fontValue===(prefs.font||'site'))));
        const output=document.getElementById('gc-text-size');if(output)output.textContent=fa(size)+'٪';
        try{localStorage.setItem('gc-theme',root.dataset.theme);}catch{}
        write('gc-personalize',prefs);
    };
    const applyA11y=()=>{
        if(a11y.length)root.dataset.a11y=a11y.join(' ');else delete root.dataset.a11y;
        document.querySelectorAll('[data-a11y-value]').forEach(button=>button.setAttribute('aria-pressed',String(a11y.includes(button.dataset.a11yValue))));
        try{if(a11y.length)localStorage.setItem('gc-a11y',JSON.stringify(a11y));else localStorage.removeItem('gc-a11y');}catch{}
    };
    applyPrefs();applyA11y();

    document.addEventListener('DOMContentLoaded',()=>{
        const panels=[['gc-personalize-toggle','gc-personalize-panel'],['gc-access-toggle','gc-access-panel']];
        const setPanel=(button,panel,open)=>{panel.hidden=!open;button.setAttribute('aria-expanded',String(open));if(open)panel.querySelector('button')?.focus();};
        panels.forEach(([buttonId,panelId])=>{
            const button=document.getElementById(buttonId),panel=document.getElementById(panelId);if(!button||!panel)return;
            button.addEventListener('click',()=>{const open=panel.hidden;panels.forEach(([otherButton,otherPanel])=>{const b=document.getElementById(otherButton),p=document.getElementById(otherPanel);if(b&&p)setPanel(b,p,false);});setPanel(button,panel,open);});
            panel.querySelector('.gc-panel-close')?.addEventListener('click',()=>{setPanel(button,panel,false);button.focus();});
        });
        document.addEventListener('click',event=>{panels.forEach(([buttonId,panelId])=>{const button=document.getElementById(buttonId),panel=document.getElementById(panelId);if(button&&panel&&!panel.hidden&&!panel.contains(event.target)&&!button.contains(event.target))setPanel(button,panel,false);});});
        document.addEventListener('keydown',event=>{if(event.key==='Escape')panels.forEach(([buttonId,panelId])=>{const button=document.getElementById(buttonId),panel=document.getElementById(panelId);if(button&&panel&&!panel.hidden){setPanel(button,panel,false);button.focus();}});});

        document.querySelectorAll('[data-theme-value]').forEach(button=>button.addEventListener('click',()=>{prefs.theme=button.dataset.themeValue;applyPrefs();}));
        document.querySelectorAll('[data-accent]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.accent)prefs.accent=button.dataset.accent;else delete prefs.accent;applyPrefs();}));
        document.querySelectorAll('[data-font-value]').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.fontValue==='system')prefs.font='system';else delete prefs.font;applyPrefs();}));
        document.getElementById('gc-text-minus')?.addEventListener('click',()=>{prefs.size=Math.max(90,(Number(prefs.size)||100)-5);if(prefs.size===100)delete prefs.size;applyPrefs();});
        document.getElementById('gc-text-plus')?.addEventListener('click',()=>{prefs.size=Math.min(130,(Number(prefs.size)||100)+5);if(prefs.size===100)delete prefs.size;applyPrefs();});
        document.getElementById('gc-personalize-reset')?.addEventListener('click',()=>{prefs={theme:'light'};applyPrefs();});

        document.querySelectorAll('[data-a11y-value]').forEach(button=>button.addEventListener('click',()=>{const value=button.dataset.a11yValue;a11y=a11y.includes(value)?a11y.filter(item=>item!==value):[...a11y,value];applyA11y();}));
        document.getElementById('gc-access-reset')?.addEventListener('click',()=>{a11y=[];applyA11y();});

        const menu=document.getElementById('gc-menu-toggle'),nav=document.getElementById('gc-primary-nav');
        menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav?.classList.toggle('is-open',open);});

        const top=document.getElementById('gc-back-to-top');if(top){top.hidden=false;const update=()=>top.classList.toggle('is-visible',window.scrollY>400);window.addEventListener('scroll',update,{passive:true});update();top.addEventListener('click',()=>window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches||a11y.includes('motion')?'auto':'smooth'}));}
    });
})();
