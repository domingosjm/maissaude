import Logo from './Logo.jsx';
import styles from './Footer.module.css';

const footerNav = [
  { label: 'Início', href: '#inicio' },
  { label: 'Sobre', href: '#institucional' },
  { label: 'Serviços', href: '#servicos' },
  { label: 'Contato', href: '#contato' },
];

const socialLinks = [
  { label: 'Instagram', href: 'https://www.instagram.com/maissaude', icon: 'instagram' },
  { label: 'Facebook', href: 'https://www.facebook.com/maissaude', icon: 'facebook' },
  { label: 'LinkedIn', href: 'https://www.linkedin.com/company/maissaude', icon: 'linkedin' },
];

function SocialIcon({ type }) {
  switch (type) {
    case 'instagram':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path
            fill="currentColor"
            d="M7 2C4.239 2 2 4.239 2 7v10c0 2.761 2.239 5 5 5h10c2.761 0 5-2.239 5-5V7c0-2.761-2.239-5-5-5H7zm0 2h10c1.654 0 3 1.346 3 3v10c0 1.654-1.346 3-3 3H7c-1.654 0-3-1.346-3-3V7c0-1.654 1.346-3 3-3zm10.5 1a1 1 0 100 2 1 1 0 000-2zM12 7a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6z"
          />
        </svg>
      );
    case 'facebook':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path
            fill="currentColor"
            d="M13 3c-2.757 0-5 2.243-5 5v3H6v4h2v9h4v-9h3l1-4h-4V8c0-.552.448-1 1-1h3V3h-3z"
          />
        </svg>
      );
    case 'linkedin':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path
            fill="currentColor"
            d="M20 3H4a1 1 0 00-1 1v16a1 1 0 001 1h16a1 1 0 001-1V4a1 1 0 00-1-1zM8.337 18.338H5.667v-8.67h2.67v8.67zM7.002 8.455a1.546 1.546 0 110-3.092 1.546 1.546 0 010 3.092zm11.335 9.883h-2.666v-4.282c0-1.022-.02-2.335-1.422-2.335-1.423 0-1.642 1.112-1.642 2.26v4.357H9.939v-8.67h2.559v1.184h.035c.356-.675 1.227-1.387 2.526-1.387 2.702 0 3.201 1.779 3.201 4.093v4.78z"
          />
        </svg>
      );
    default:
      return null;
  }
}

function Footer() {
  const currentYear = new Date().getFullYear();
  return (
    <footer className={styles.footer}>
      <div className={styles.container}>
        <div className={styles.branding}>
          <Logo size={48} />
          <p>
            Mais Saúde é uma clínica multidisciplinar dedicada a oferecer cuidado humanizado e soluções completas em
            saúde para toda a família.
          </p>
        </div>
        <nav aria-label="Navegação do rodapé" className={styles.nav}>
          <h3>Navegação</h3>
          <ul>
            {footerNav.map((item) => (
              <li key={item.href}>
                <a href={item.href}>{item.label}</a>
              </li>
            ))}
          </ul>
        </nav>
        <div className={styles.social}>
          <h3>Redes sociais</h3>
          <ul>
            {socialLinks.map((item) => (
              <li key={item.href}>
                <a href={item.href} aria-label={`Visitar ${item.label} da Clínica Mais Saúde`}>
                  <SocialIcon type={item.icon} />
                </a>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div className={styles.bottomBar}>
        <p>© {currentYear} Clínica Mais Saúde. Todos os direitos reservados.</p>
        <div className={styles.policies}>
          <a href="#">Política de Privacidade</a>
          <a href="#">Termos de Uso</a>
        </div>
      </div>
    </footer>
  );
}

export default Footer;
