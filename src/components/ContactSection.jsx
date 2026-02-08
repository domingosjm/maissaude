import styles from './ContactSection.module.css';

function ContactSection() {
  return (
    <section id="contato" className={styles.section} aria-labelledby="contato-heading">
      <div className={styles.container}>
        <div className={styles.header}>
          <span className={styles.kicker}>Contato</span>
          <h2 id="contato-heading">Vamos cuidar da sua saúde juntos</h2>
          <p>Preencha o formulário e nossa equipe retornará rapidamente para confirmar sua consulta.</p>
        </div>
        <div className={styles.content}>
          <form className={styles.form} aria-describedby="contato-instructions">
            <p id="contato-instructions" className={styles.instructions}>
              Todos os campos são obrigatórios. Seus dados são protegidos e usados apenas para retorno do contato.
            </p>
            <div className={styles.grid}>
              <label className={styles.field}>
                <span>Nome completo</span>
                <input name="nome" type="text" autoComplete="name" required />
              </label>
              <label className={styles.field}>
                <span>E-mail</span>
                <input name="email" type="email" autoComplete="email" required />
              </label>
              <label className={styles.field}>
                <span>Telefone</span>
                <input name="telefone" type="tel" autoComplete="tel" required />
              </label>
              <label className={styles.field}>
                <span>Assunto</span>
                <select name="assunto" required>
                  <option value="">Selecione uma opção</option>
                  <option value="consulta">Agendamento de consulta</option>
                  <option value="exame">Agendamento de exame</option>
                  <option value="duvida">Dúvidas gerais</option>
                </select>
              </label>
            </div>
            <label className={styles.field}>
              <span>Mensagem</span>
              <textarea name="mensagem" rows="4" required></textarea>
            </label>
            <button type="submit" className={styles.submitButton}>
              Enviar mensagem
            </button>
          </form>
          <div className={styles.details}>
            <div className={styles.contactCard}>
              <h3>Informações de contato</h3>
              <ul>
                <li>
                  <strong>Telefone:</strong>{' '}
                  <a href="tel:+551140001234">(11) 4000-1234</a>
                </li>
                <li>
                  <strong>E-mail:</strong>{' '}
                  <a href="mailto:contato@maissaude.com">contato@maissaude.com</a>
                </li>
                <li>
                  <strong>Endereço:</strong> Av. Exemplo, 123 - Centro, São Paulo - SP
                </li>
                <li>
                  <strong>Horário:</strong> Segunda a sábado, das 7h às 21h
                </li>
              </ul>
            </div>
            <div className={styles.mapWrapper}>
              <iframe
                title="Localização da Clínica Mais Saúde"
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3656.904422271337!2d-46.65657192373562!3d-23.573322662174763!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce59c690d33c07%3A0x1373efbcd5296a0!2sPaulista%20Avenida!5e0!3m2!1spt-BR!2sbr!4v1715972400000!5m2!1spt-BR!2sbr"
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                allowFullScreen
              ></iframe>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default ContactSection;
