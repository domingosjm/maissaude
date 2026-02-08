import styles from './InstitutionalSection.module.css';

const values = [
  {
    title: 'Atendimento humanizado',
    description:
      'Escuta ativa, empatia e respeito a cada história, com acolhimento desde a primeira consulta.',
  },
  {
    title: 'Tecnologia a favor da saúde',
    description:
      'Equipamentos modernos e protocolos integrados que garantem diagnósticos ágeis e precisos.',
  },
  {
    title: 'Planos de cuidado personalizados',
    description:
      'Equipe multidisciplinar alinhada para desenhar planos individualizados para você e sua família.',
  },
];

function InstitutionalSection() {
  return (
    <section id="institucional" className={styles.section} aria-labelledby="institucional-heading">
      <div className={styles.container}>
        <div className={styles.intro}>
          <span className={styles.kicker}>Quem somos</span>
          <h2 id="institucional-heading">Cuidamos das pessoas antes de cuidar das doenças</h2>
          <p>
            Na Mais Saúde, acreditamos que cada paciente merece um olhar atento e integral. Nossa equipe clínico
            assistencial une experiência médica, acolhimento e inovação para promover saúde preventiva, diagnóstica e
            reabilitadora. Mantemos uma comunicação transparente com pacientes, familiares e parceiros para construir
            relações de confiança duradouras.
          </p>
        </div>
        <div className={styles.values}>
          {values.map((value) => (
            <article key={value.title} className={styles.valueCard}>
              <h3>{value.title}</h3>
              <p>{value.description}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

export default InstitutionalSection;
