import Logo from './Logo.jsx';
import styles from './Header.module.css';

const navItems = [
  { label: 'Sobre', href: '#institucional' },
  { label: 'Serviços', href: '#servicos' },
  { label: 'Diferenciais', href: '#diferenciais' },
  { label: 'Depoimentos', href: '#depoimentos' },
  { label: 'Blog', href: '#blog' },
  { label: 'Contato', href: '#contato' },
];

function Header() {
  return (
    <header className={styles.header}>
      <div className={styles.container}>
        <a href="#inicio" className={styles.logoLink} aria-label="Ir para o início da página">
          <Logo size={48} />
        </a>
        <nav aria-label="Navegação principal" className={styles.nav}>
          <ul className={styles.navList}>
            {navItems.map((item) => (
              <li key={item.href}>
                <a href={item.href} className={styles.navLink}>
                  {item.label}
                </a>
              </li>
            ))}
          </ul>
        </nav>
        <a href="#contato" className={styles.contactLink}>
          Fale conosco
        </a>
      </div>
    </header>
  );
}

export default Header;
