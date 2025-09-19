import theme from '../styles/theme.js';
import styles from './Logo.module.css';

function Logo({ size = 56, showText = true }) {
  return (
    <div className={styles.logo} aria-label="Logotipo Clínica Mais Saúde">
      <svg
        className={styles.icon}
        width={size}
        height={size}
        viewBox="0 0 64 64"
        role="img"
        aria-hidden="true"
      >
        <path
          d="M32 58c-1.1 0-2.1-0.4-2.9-1.1l-0.4-0.3C17.1 47.6 8 39.6 8 27.6 8 18 15.5 10.5 25.1 10.5c3.2 0 6.2 0.9 8.9 2.7 2.7-1.8 5.7-2.7 8.9-2.7C52.5 10.5 60 18 60 27.6c0 12-9.1 20-20.7 29l-0.4 0.3c-0.8 0.7-1.8 1.1-2.9 1.1z"
          fill={theme.colors.primary}
        />
        <rect x="27" y="22" width="10" height="20" rx="2" fill={theme.colors.secondary} />
        <rect x="22" y="27" width="20" height="10" rx="2" fill={theme.colors.secondary} />
      </svg>
      {showText && (
        <span className={styles.text}>
          <span>Mais</span>
          <span>Saúde</span>
        </span>
      )}
    </div>
  );
}

export default Logo;
