import Header from '../components/Header.jsx';
import HeroSection from '../components/HeroSection.jsx';
import InstitutionalSection from '../components/InstitutionalSection.jsx';
import ServicesSection from '../components/ServicesSection.jsx';
import DifferentialsSection from '../components/DifferentialsSection.jsx';
import TestimonialsSection from '../components/TestimonialsSection.jsx';
import SecondaryCtaSection from '../components/SecondaryCtaSection.jsx';
import BlogSection from '../components/BlogSection.jsx';
import ContactSection from '../components/ContactSection.jsx';
import Footer from '../components/Footer.jsx';
import styles from './HomePage.module.css';

function HomePage() {
  return (
    <div className={styles.page}>
      <a href="#conteudo-principal" className={styles.skipLink}>
        Pular para o conteúdo principal
      </a>
      <Header />
      <main id="conteudo-principal" tabIndex={-1}>
        <HeroSection />
        <InstitutionalSection />
        <ServicesSection />
        <DifferentialsSection />
        <TestimonialsSection />
        <SecondaryCtaSection />
        <BlogSection />
        <ContactSection />
      </main>
      <Footer />
    </div>
  );
}

export default HomePage;
