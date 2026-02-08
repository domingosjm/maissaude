import styles from './SecondaryCtaSection.module.css';

function SecondaryCtaSection() {
  return (
    <section className={styles.section} aria-labelledby="whatsapp-heading">
      <div className={styles.container}>
        <div>
          <span className={styles.kicker}>Precisa falar agora?</span>
          <h2 id="whatsapp-heading">Estamos a uma mensagem de distância</h2>
          <p>
            Tire dúvidas rápidas, antecipe autorizações ou receba orientações antes da consulta. Nossa equipe de
            relacionamento está disponível no WhatsApp para ajudar você a cada passo.
          </p>
        </div>
        <a
          className={styles.whatsAppButton}
          href="https://wa.me/5511999999999"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Conversar com a Clínica Mais Saúde pelo WhatsApp"
        >
          <svg
            aria-hidden="true"
            focusable="false"
            width="28"
            height="28"
            viewBox="0 0 32 32"
            className={styles.icon}
          >
            <path
              d="M16.011 3C9.382 3 4 8.383 4 15.011c0 2.465.744 4.746 2.02 6.648L4 29l7.557-2.033c1.835 1.006 3.935 1.579 6.123 1.579C22.64 28.546 28 23.163 28 16.535 28 9.908 22.64 4.525 16.011 4.525Zm0 23.473c-1.935 0-3.75-.517-5.316-1.419l-.38-.221-4.483 1.206 1.196-4.371-.247-.38c-1.17-1.796-1.79-3.882-1.79-6.003 0-6.047 4.967-11.003 11.02-11.003 6.048 0 11.015 4.956 11.015 11.003 0 6.042-4.967 10.988-11.015 10.988Zm6.043-8.249c-.33-.166-1.95-.965-2.253-1.075-.303-.11-.525-.166-.744.167-.22.33-.858 1.074-1.053 1.294-.193.22-.387.248-.717.082-.33-.166-1.395-.515-2.66-1.646-.984-.877-1.648-1.962-1.843-2.293-.193-.33-.02-.508.147-.674.152-.152.33-.387.495-.58.166-.193.22-.33.33-.55.11-.22.055-.414-.027-.58-.082-.166-.744-1.8-1.02-2.465-.267-.64-.538-.55-.744-.561-.193-.011-.414-.011-.635-.011-.22 0-.58.083-.884.414-.303.33-1.16 1.134-1.16 2.767 0 1.634 1.19 3.207 1.357 3.428.166.22 2.345 3.58 5.684 4.867.794.274 1.414.438 1.896.561.795.152 1.52.083 2.093.055.64-.028 1.95-.796 2.228-1.565.275-.77.275-1.429.193-1.565-.083-.138-.303-.22-.633-.387Z"
              fill="currentColor"
            />
          </svg>
          Falar pelo WhatsApp
        </a>
      </div>
    </section>
  );
}

export default SecondaryCtaSection;
