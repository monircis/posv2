// Sign-in page: work email + password.
// Staff come from the sample users in User Management. This runs in the
// browser only, so it is a working front end for a real sign-in API, not a
// replacement for one: passwords must be checked on the server.
(function () {
  'use strict';

  const USERS = [
    { name:'Aminul Haque', email:'aminul@madinah.com.au', password:'owner123', role:'Owner', locations:'All', status:'active' },
    { name:'Sara Rahman', email:'sara.rahman@madinah.com.au', password:'manager123', role:'Manager', locations:'Main branch', status:'active' },
    { name:'Yusuf Karim', email:'yusuf.k@madinah.com.au', password:'cashier123', role:'Cashier', locations:'Main branch', status:'active' },
    { name:'Fatima Noor', email:'fatima.noor@madinah.com.au', password:'cashier123', role:'Cashier', locations:'Main branch', status:'active' },
    { name:'Imran Sheikh', email:'imran@madinah.com.au', password:'kitchen123', role:'Kitchen', locations:'Main branch', status:'active' },
    { name:'Nadia Islam', email:'nadia.islam@madinah.com.au', password:'accounts123', role:'Accountant', locations:'All', status:'active' },
    { name:'Omar Siddiq', email:'omar.siddiq@madinah.com.au', password:'', role:'Stock keeper', locations:'Cold store', status:'invited' },
    { name:'Rashid Alam', email:'rashid.alam@madinah.com.au', password:'cashier123', role:'Cashier', locations:'Main branch', status:'off' }
  ];

  // Where each role lands after signing in.
  const HOME = { Owner:'index.html', Manager:'index.html', Accountant:'accounting.html', 'Stock keeper':'products.html', Cashier:'pos.html', Kitchen:'kitchen.html' };
  const MAX_TRIES = 5, LOCK_SECONDS = 30;

  const $ = id => document.getElementById(id);
  const initials = n => n.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase();
  // Staff tied to one branch may pick that branch or "All locations" (which then means their branch).
  const canUse = (u, loc) => u.locations === 'All' || loc === 'All' || loc === u.locations;

  const form = $('pass-form'), email = $('email'), password = $('password'), alertBox = $('pass-alert'), submit = $('pass-submit');
  let tries = 0, lockedUntil = 0, lockTimer = null;

  // Only accept a page in this folder as the return address.
  function nextPage(user) {
    const next = new URLSearchParams(location.search).get('next');
    if (next && /^[a-z-]+\.html$/.test(next) && next !== 'login.html') return next;
    return HOME[user.role] || 'index.html';
  }

  function showAlert(text, tone) {
    alertBox.textContent = text;
    alertBox.className = 'alert' + (tone ? ' ' + tone : '');
    alertBox.hidden = !text;
  }

  function fieldError(input, errEl, text) {
    errEl.textContent = text;
    input.setAttribute('aria-invalid', text ? 'true' : 'false');
    if (text) input.setAttribute('aria-describedby', errEl.id); else input.removeAttribute('aria-describedby');
  }

  function accountProblem(u) {
    if (u.status === 'off') return u.name + '’s account is deactivated. Ask the owner to reactivate it in User Management.';
    if (u.status === 'invited') return u.name + ' hasn’t accepted the invitation yet. Use the link in the invite email to set a password.';
    return '';
  }

  function startLock() {
    lockedUntil = Date.now() + LOCK_SECONDS * 1000;
    submit.disabled = true;
    const tick = () => {
      const left = Math.ceil((lockedUntil - Date.now()) / 1000);
      if (left <= 0) { clearInterval(lockTimer); tries = 0; submit.disabled = false; showAlert(''); return; }
      showAlert('Too many attempts. Try again in ' + left + ' second' + (left === 1 ? '' : 's') + '.', 'warn');
    };
    tick();
    lockTimer = setInterval(tick, 1000);
  }

  function finish(user, loc, remember) {
    if (loc === 'All' && user.locations !== 'All') loc = user.locations;
    const who = JSON.stringify({ name: user.name, initials: initials(user.name), email: user.email, role: user.role, location: loc });
    try { (remember ? localStorage : sessionStorage).setItem('erp.user', who); } catch (e) {}
    submit.disabled = true;
    submit.classList.add('busy');
    submit.textContent = 'Signing in';
    setTimeout(() => { location.href = nextPage(user); }, 350);
  }

  email.addEventListener('input', () => fieldError(email, $('email-err'), ''));
  password.addEventListener('input', () => fieldError(password, $('password-err'), ''));

  $('toggle-pw').addEventListener('click', e => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    e.currentTarget.textContent = show ? 'Hide' : 'Show';
    e.currentTarget.setAttribute('aria-pressed', show ? 'true' : 'false');
    e.currentTarget.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    password.focus();
  });

  $('forgot').addEventListener('click', () => {
    showAlert('Ask the business owner to send you a reset link from User Management. Demo passwords are listed under Demo accounts below.', 'info');
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (Date.now() < lockedUntil) return;
    showAlert('');
    const em = email.value.trim().toLowerCase(), pw = password.value;
    let bad = false;
    if (!em) { fieldError(email, $('email-err'), 'Enter your work email.'); bad = true; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { fieldError(email, $('email-err'), 'That doesn’t look like an email address.'); bad = true; }
    if (!pw) { fieldError(password, $('password-err'), 'Enter your password.'); bad = true; }
    if (bad) { (email.getAttribute('aria-invalid') === 'true' ? email : password).focus(); return; }

    const u = USERS.find(x => x.email === em);
    const problem = u && accountProblem(u);
    if (problem) { showAlert(problem); return; }
    if (!u || u.password !== pw) {
      tries++;
      if (tries >= MAX_TRIES) { startLock(); return; }
      showAlert('Email or password is incorrect. ' + (MAX_TRIES - tries) + ' attempt' + (MAX_TRIES - tries === 1 ? '' : 's') + ' left.');
      password.select();
      return;
    }
    const loc = $('location').value;
    if (!canUse(u, loc)) {
      showAlert(u.name.split(' ')[0] + ' can only sign in at ' + u.locations + '. Pick that location or All locations.');
      $('location').focus();
      return;
    }
    finish(u, loc, $('remember').checked);
  });

  // ---------- Demo accounts ----------
  const list = $('demo-list');
  USERS.forEach(u => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'demo-row' + (u.status === 'active' ? '' : ' off');
    b.innerHTML = '<span class="av"></span><span class="who"><span class="nm"></span><span class="rl"></span></span><span class="cred"></span>';
    b.querySelector('.av').textContent = initials(u.name);
    b.querySelector('.nm').textContent = u.name;
    b.querySelector('.rl').textContent = u.role + ' · ' + (u.locations === 'All' ? 'All locations' : u.locations);
    b.querySelector('.cred').textContent = u.status === 'active' ? u.password : u.status === 'off' ? 'deactivated' : 'invited';
    b.addEventListener('click', () => {
      email.value = u.email;
      password.value = u.password;
      $('location').value = u.locations === 'All' ? 'All' : u.locations;
      fieldError(email, $('email-err'), '');
      fieldError(password, $('password-err'), '');
      showAlert('');
      submit.focus();
    });
    list.appendChild(b);
  });

  // ---------- Start ----------
  const params = new URLSearchParams(location.search);
  if (params.get('signedout')) showAlert('You’ve signed out.', 'info');
  else if (params.get('next')) showAlert('Sign in to continue.', 'info');
  email.focus();
})();
