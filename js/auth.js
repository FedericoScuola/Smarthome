/**
 * SmartNest — Authentication Module
 * Handles login, logout, session management
 */

'use strict';

const Auth = (() => {

  /* ── Mock user database ── */
  const USERS = [
    {
      id: 'usr_001',
      name: 'Marco Alberti',
      initials: 'MA',
      email: 'marco@smartnest.io',
      password: 'admin123',
      role: 'admin',
      color: 'linear-gradient(135deg,#00d4b4,#0088aa)',
      telegram: '@marco_tg',
    },
    {
      id: 'usr_002',
      name: 'Sara Ferrari',
      initials: 'SF',
      email: 'sara@smartnest.io',
      password: 'admin123',
      role: 'admin',
      color: 'linear-gradient(135deg,#f0a030,#f04060)',
      telegram: '@sara_tg',
    },
    {
      id: 'usr_003',
      name: 'Giulia Bianchi',
      initials: 'GB',
      email: 'giulia@smartnest.io',
      password: 'viewer123',
      role: 'viewer',
      color: 'linear-gradient(135deg,#4a5878,#1a2130)',
      telegram: null,
    },
  ];

  const SESSION_KEY = 'smartnest_session';

  /* ── Public API ── */
  return {
    /**
     * Attempt login with credentials.
     * @param {string} email
     * @param {string} password
     * @returns {{ success: boolean, user?: object, error?: string }}
     */
    login(email, password) {
      if (!email || !password) {
        return { success: false, error: 'Inserisci email e password.' };
      }
      const user = USERS.find(
        u => u.email.toLowerCase() === email.toLowerCase().trim()
      );
      if (!user) {
        return { success: false, error: 'Account non trovato.' };
      }
      if (user.password !== password) {
        return { success: false, error: 'Password errata.' };
      }
      const session = {
        userId:    user.id,
        name:      user.name,
        initials:  user.initials,
        email:     user.email,
        role:      user.role,
        color:     user.color,
        telegram:  user.telegram,
        loginAt:   new Date().toISOString(),
      };
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
      return { success: true, user: session };
    },

    /** Log out and clear session. */
    logout() {
      sessionStorage.removeItem(SESSION_KEY);
    },

    /**
     * Get the current session, or null if not logged in.
     * @returns {object|null}
     */
    getSession() {
      try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch {
        return null;
      }
    },

    /** @returns {boolean} */
    isAuthenticated() {
      return this.getSession() !== null;
    },

    /** @returns {boolean} */
    isAdmin() {
      const s = this.getSession();
      return s?.role === 'admin';
    },

    /**
     * Quick-login with a preset demo account.
     * @param {string} email
     */
    loginAsDemo(email) {
      const user = USERS.find(u => u.email === email);
      if (user) {
        return this.login(user.email, user.password);
      }
      return { success: false, error: 'Demo non trovato.' };
    },

    /** Expose user list (without passwords) for admin views */
    getUsers() {
      return USERS.map(({ password: _, ...u }) => u);
    },
  };

})();

window.Auth = Auth;
