import styles from './TestimonialsSection.module.css';

const testimonials = [
  {
    name: 'Fernanda Oliveira',
    role: 'Paciente de Ginecologia',
    statement:
      'Fui acolhida desde a recepção. A equipe explicou cada etapa do tratamento com muita empatia e clareza.',
  },
  {
    name: 'Ricardo Santos',
    role: 'Paciente de Fisioterapia',
    statement:
      'Depois da cirurgia precisei de um acompanhamento intenso e encontrei profissionais dedicados e atenciosos.',
  },
  {
    name: 'Patrícia Lima',
    role: 'Mãe do Daniel, 5 anos',
    statement:
      'A pediatra nos ajudou com orientações práticas e um plano personalizado. Meu filho adora ir às consultas.',
  },
];

function TestimonialsSection() {
  return (
    <section id="depoimentos" className={styles.section} aria-labelledby="depoimentos-heading">
      <div className={styles.container}>
        <div className={styles.header}>
          <span className={styles.kicker}>Histórias reais</span>
          <h2 id="depoimentos-heading">Depoimentos de quem confia na Mais Saúde</h2>
        </div>
        <div className={styles.grid}>
          {testimonials.map((item) => (
            <figure key={item.name} className={styles.card}>
              <blockquote>
                <p>“{item.statement}”</p>
              </blockquote>
              <figcaption>
                <span className={styles.name}>{item.name}</span>
                <span className={styles.role}>{item.role}</span>
              </figcaption>
            </figure>
          ))}
        </div>
      </div>
    </section>
  );
}

export default TestimonialsSection;
