import styles from './ServicesSection.module.css';

const services = [
  {
    title: 'Medicina Geral',
    description:
      'Avaliação completa da saúde com foco em prevenção, diagnóstico precoce e acompanhamento contínuo.',
  },
  {
    title: 'Pediatria',
    description:
      'Cuidado atento para bebês, crianças e adolescentes com orientação às famílias em cada fase do crescimento.',
  },
  {
    title: 'Ginecologia',
    description:
      'Saúde integral da mulher, incluindo acompanhamento ginecológico, preventivos e planejamento familiar.',
  },
  {
    title: 'Nutrição',
    description:
      'Planos alimentares personalizados que consideram estilo de vida, restrições e metas de bem-estar.',
  },
  {
    title: 'Fisioterapia',
    description:
      'Protocolos de reabilitação e prevenção de lesões com recursos modernos e acompanhamento especializado.',
  },
  {
    title: 'Psicologia',
    description:
      'Atendimento acolhedor para saúde emocional, com sessões individuais, familiares e em grupo.',
  },
  {
    title: 'Diagnóstico por Imagem',
    description:
      'Exames de imagem com tecnologia de ponta, laudos ágeis e integração direta com o corpo clínico.',
  },
];

function ServicesSection() {
  return (
    <section id="servicos" className={styles.section} aria-labelledby="servicos-heading">
      <div className={styles.container}>
        <div className={styles.header}>
          <span className={styles.kicker}>Especialidades</span>
          <h2 id="servicos-heading">Serviços que cuidam da sua saúde de ponta a ponta</h2>
          <p>
            Nossa rede de profissionais integra diferentes áreas para oferecer uma experiência completa, com linhas de
            cuidado coordenadas e comunicação constante entre as equipes.
          </p>
        </div>
        <div className={styles.grid}>
          {services.map((service) => (
            <article key={service.title} className={styles.card}>
              <h3>{service.title}</h3>
              <p>{service.description}</p>
              <a className={styles.linkButton} href="#contato" aria-label={`Saiba mais sobre ${service.title}`}>
                Saiba mais
              </a>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

export default ServicesSection;
