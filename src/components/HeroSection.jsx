import Logo from './Logo.jsx';
import styles from './HeroSection.module.css';

function HeroSection() {
  return (
    <section id="inicio" className={styles.hero} aria-labelledby="hero-heading">
      <div className={styles.contentWrapper}>
        <div className={styles.textColumn}>
          <Logo size={72} />
          <h1 id="hero-heading" className={styles.title}>
            Cuidado integral e humanizado para toda a família
          </h1>
          <p className={styles.subtitle}>
            A Clínica Mais Saúde reúne especialistas dedicados a oferecer um atendimento acolhedor,
            seguro e personalizado. Da prevenção ao diagnóstico, estamos ao seu lado em cada etapa da jornada.
          </p>
          <div className={styles.actions}>
            <a
              href="#contato"
              className={styles.primaryCta}
              aria-label="Agende sua consulta na Clínica Mais Saúde"
            >
              Agende sua consulta
            </a>
            <a href="#servicos" className={styles.secondaryCta}>
              Conheça nossos serviços
            </a>
          </div>
          <ul className={styles.highlights}>
            <li>
              <span aria-hidden="true">✓</span>
              Equipe multidisciplinar experiente
            </li>
            <li>
              <span aria-hidden="true">✓</span>
              Estrutura moderna e acolhedora
            </li>
            <li>
              <span aria-hidden="true">✓</span>
              Planos personalizados de cuidado
            </li>
          </ul>
        </div>
        <div className={styles.visualColumn}>
          <div className={styles.card}>
            <h2>Seu bem-estar começa aqui</h2>
            <p>
              Consultas presenciais e telemedicina com profissionais preparados para oferecer diagnósticos
              precisos e acompanhamento contínuo.
            </p>
            <div className={styles.scheduleBox}>
              <span className={styles.scheduleLabel}>Horários disponíveis</span>
              <span className={styles.scheduleValue}>Seg - Sáb • 7h às 21h</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default HeroSection;
