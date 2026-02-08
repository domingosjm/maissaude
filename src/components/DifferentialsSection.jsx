import styles from './DifferentialsSection.module.css';

const differentials = [
  {
    title: 'Equipe interdisciplinar',
    detail: 'Profissionais que atuam de forma integrada, discutindo casos em rounds semanais.',
  },
  {
    title: 'Telemedicina segura',
    detail: 'Consultas online com prontuário eletrônico e assinatura digital para laudos e prescrições.',
  },
  {
    title: 'Ambiente acolhedor',
    detail: 'Espaços pensados para o conforto, com salas infantis, aromaterapia e musicoterapia.',
  },
  {
    title: 'Programa de prevenção',
    detail: 'Check-ups personalizados com lembretes automáticos e acompanhamento pós-consulta.',
  },
];

function DifferentialsSection() {
  return (
    <section id="diferenciais" className={styles.section} aria-labelledby="diferenciais-heading">
      <div className={styles.container}>
        <div className={styles.header}>
          <span className={styles.kicker}>Por que escolher a Mais Saúde</span>
          <h2 id="diferenciais-heading">Diferenciais que geram confiança e resultados</h2>
        </div>
        <div className={styles.grid}>
          {differentials.map((item) => (
            <article key={item.title} className={styles.card}>
              <h3>{item.title}</h3>
              <p>{item.detail}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

export default DifferentialsSection;
