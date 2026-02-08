import styles from './BlogSection.module.css';

const posts = [
  {
    title: '5 hábitos para fortalecer sua imunidade',
    summary: 'Orientações simples para incluir no dia a dia e reforçar seu sistema imunológico durante todo o ano.',
    href: '#',
  },
  {
    title: 'Como preparar as crianças para a consulta médica',
    summary: 'Dicas da nossa equipe de pediatria para transformar a consulta em um momento de confiança e cuidado.',
    href: '#',
  },
  {
    title: 'Alimentação e saúde mental: qual a relação?',
    summary:
      'Veja como escolhas alimentares podem influenciar diretamente seu equilíbrio emocional e produtividade.',
    href: '#',
  },
];

function BlogSection() {
  return (
    <section id="blog" className={styles.section} aria-labelledby="blog-heading">
      <div className={styles.container}>
        <div className={styles.header}>
          <span className={styles.kicker}>Blog & dicas</span>
          <h2 id="blog-heading">Conteúdos para cuidar da sua saúde todos os dias</h2>
          <p>
            Informação confiável e linguagem leve para apoiar a sua rotina de prevenção, autocuidado e bem-estar.
          </p>
        </div>
        <div className={styles.grid}>
          {posts.map((post) => (
            <article key={post.title} className={styles.card}>
              <h3>{post.title}</h3>
              <p>{post.summary}</p>
              <a className={styles.link} href={post.href} aria-label={`Ler artigo: ${post.title}`}>
                Ler artigo
              </a>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

export default BlogSection;
