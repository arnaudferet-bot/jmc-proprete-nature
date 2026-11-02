const menu=document.querySelector('.menu');const nav=document.querySelector('nav');menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open)});nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{menu.setAttribute('aria-expanded','false');nav.classList.remove('open')}));document.querySelectorAll('[data-service]').forEach(a=>a.addEventListener('click',()=>document.querySelector('[name=activite]').value=a.dataset.service));document.querySelectorAll('[data-profile]').forEach(a=>a.addEventListener('click',()=>document.querySelector('[name=profil]').value=a.dataset.profile));document.querySelectorAll('[data-frequency]').forEach(a=>a.addEventListener('click',()=>{const field=document.querySelector('[name=message]');if(!field.value.includes(a.dataset.frequency))field.value=(field.value?field.value+'\n':'')+a.dataset.frequency}));document.addEventListener('keydown',e=>{if(e.key==='Escape'){menu.setAttribute('aria-expanded','false');nav.classList.remove('open')}});

const contactForm = document.querySelector('#contact-form');
const contactResult = document.querySelector('#form-result');
const sendButton = contactForm.querySelector('button[type="submit"]');
let tokenPromise = null;
function showContactResult(message, error = false) {
  contactResult.hidden = false;
  contactResult.textContent = message;
  contactResult.dataset.state = error ? 'error' : 'success';
  contactResult.focus({preventScroll:true});
}
async function requestContact(options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);
  try {
    const response = await fetch(contactForm.getAttribute('action'), {...options, credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{'Accept':'application/json'}});
    if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Le formulaire nécessite son traitement PHP sur l’hébergement OVH. Aucun envoi n’a été confirmé. Votre saisie est conservée.');
    const result = await response.json();
    if (!response.ok || result.ok !== true) {
      if (result.refresh) tokenPromise = null;
      throw new Error(result.message || 'L’envoi est indisponible. Votre saisie est conservée. Veuillez réessayer plus tard.');
    }
    return result;
  } finally { clearTimeout(timeout); }
}
function contactToken() {
  if (!tokenPromise) tokenPromise = requestContact().then(result => {
    if (typeof result.token !== 'string' || !/^[a-f0-9]{64}$/.test(result.token)) throw new Error('La protection du formulaire n’a pas pu être initialisée. Aucun envoi effectué.');
    return result.token;
  }).catch(error => { tokenPromise = null; throw error; });
  return tokenPromise;
}
// Initialisation au premier usage : aucun cookie du formulaire avant interaction.
contactForm.addEventListener('focusin', () => { contactToken().catch(() => {}); });
contactForm.addEventListener('submit', async event => {
  event.preventDefault();
  if (sendButton.disabled || !contactForm.reportValidity()) return;
  sendButton.disabled = true;
  sendButton.textContent = 'Envoi en cours…';
  contactForm.setAttribute('aria-busy','true');
  contactResult.hidden = true;
  try {
    const token = await contactToken();
    const data = new FormData(contactForm);
    data.set('token', token);
    await requestContact({method:'POST',body:data});
    contactForm.reset();
    tokenPromise = null;
    showContactResult('Votre demande a bien été envoyée. Merci de votre confiance ! Nous reviendrons vers vous prochainement.');
  } catch (error) {
    showContactResult(error.name === 'AbortError' ? 'Le délai de réponse est dépassé : l’envoi n’a pas pu être confirmé. Vos informations sont conservées. Une nouvelle tentative avec ce formulaire évitera un second envoi si la première a déjà été acceptée.' : (error instanceof TypeError ? 'Une erreur réseau empêche de confirmer l’envoi. Votre saisie est conservée.' : error.message) || 'L’envoi n’a pas pu être confirmé. Votre saisie est conservée.', true);
  } finally {
    sendButton.disabled = false;
    sendButton.textContent = 'Envoyer ma demande ↗';
    contactForm.removeAttribute('aria-busy');
  }
});
