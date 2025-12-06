# Guia de Teste Rápido - Sistema Fullstack

## 🚀 Passo a Passo para Testar

### 1. Configurar Banco de Dados

```powershell
# Acesse o MySQL
mysql -u root -p

# Execute os comandos
USE mais_saude;
SOURCE C:\Users\Domingos J. Mangação\OneDrive\Desktop\Projectos\MSLDA\sql\api_tables.sql;
```

Ou execute manualmente via phpMyAdmin/HeidiSQL.

### 2. Verificar Servidor Apache/PHP

Certifique-se de que:
- Apache está rodando
- PHP está habilitado
- O projeto está em `C:\xampp\htdocs\MSLDA` ou similar

### 3. Criar Usuário de Teste

```sql
INSERT INTO users (name, email, password, role) 
VALUES (
  'Admin Teste',
  'admin@test.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
  'admin'
);
```

### 4. Testar Backend (API)

Abra no navegador ou Postman:

**Teste 1: Dashboard**
```
GET http://localhost/MSLDA/api/dashboard.php
```

Deve retornar erro 401 (não autenticado) - correto!

**Teste 2: Login**
```
POST http://localhost/MSLDA/api/auth.php
Content-Type: application/json

{
  "email": "admin@test.com",
  "password": "password"
}
```

Deve retornar:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Admin Teste",
    "email": "admin@test.com"
  }
}
```

**Teste 3: Dashboard Autenticado**
```
GET http://localhost/MSLDA/api/dashboard.php
```

Agora deve retornar as estatísticas!

### 5. Testar Frontend

```powershell
cd "C:\Users\Domingos J. Mangação\OneDrive\Desktop\Projectos\MSLDA\frontend"
npm run dev
```

Acesse: http://localhost:3001

**Login:**
- Email: `admin@test.com`
- Senha: `password`

### 6. Verificar Integração

Após login no frontend:
1. Dashboard deve carregar
2. Ir para "Recepção"
3. Deve ver estatísticas carregadas da API
4. Se aparecer números (mesmo que zeros), funcionou! ✅

## 🔧 Problemas Comuns

### CORS Error
Se aparecer erro de CORS no console do navegador:

**Solução 1:** Verificar se o `.htaccess` está na pasta `/api/`

**Solução 2:** Adicionar no `php.ini`:
```ini
header('Access-Control-Allow-Origin: http://localhost:3001');
header('Access-Control-Allow-Credentials: true');
```

### Erro 404 na API
- Verificar se o Apache está rodando
- Verificar se a pasta está no `htdocs`
- Verificar se o mod_rewrite está habilitado

### Erro de Conexão MySQL
Verificar `config.php`:
```php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // Sua senha
$DB_NAME = 'mais_saude';
```

### Tabelas não existem
Execute novamente:
```sql
SOURCE C:\Users\Domingos J. Mangação\OneDrive\Desktop\Projectos\MSLDA\sql\api_tables.sql;
```

## ✅ Checklist de Funcionamento

- [ ] Banco de dados criado
- [ ] Tabelas criadas
- [ ] Usuário de teste criado
- [ ] Apache rodando
- [ ] PHP funcionando
- [ ] API respondendo
- [ ] Frontend rodando (porta 3001)
- [ ] Login funcionando
- [ ] Dashboard carregando
- [ ] Recepção mostrando estatísticas

## 📊 Dados de Teste

Para popular o banco com dados de teste:

```sql
-- Pacientes
INSERT INTO patients (name, cpf, birth_date, phone, email) VALUES
('João Silva', '123.456.789-00', '1990-01-15', '(11) 98765-4321', 'joao@email.com'),
('Maria Santos', '987.654.321-00', '1985-05-20', '(11) 97654-3210', 'maria@email.com'),
('Pedro Costa', '456.789.123-00', '1992-08-10', '(11) 96543-2109', 'pedro@email.com');

-- Agendamentos
INSERT INTO appointments (patient_id, appointment_date, status, created_by) VALUES
(1, '2025-12-01 14:00:00', 'scheduled', 1),
(2, '2025-12-01 15:00:00', 'scheduled', 1),
(3, '2025-12-01 16:00:00', 'confirmed', 1);

-- Exames
INSERT INTO lab_exams (patient_id, exam_type_id, status, requested_by) VALUES
(1, 1, 'pending', 1),
(2, 2, 'completed', 1),
(3, 3, 'pending', 1);

-- Medicamentos
INSERT INTO medications (name, description, manufacturer, created_by) VALUES
('Paracetamol 500mg', 'Analgésico e antitérmico', 'EMS', 1),
('Amoxicilina 500mg', 'Antibiótico', 'Medley', 1),
('Dipirona 1g', 'Analgésico', 'Neo Química', 1);

-- Stock
INSERT INTO medication_stock (medication_id, quantity, minimum_stock, unit) VALUES
(1, 500, 50, 'comprimido'),
(2, 200, 30, 'cápsula'),
(3, 150, 20, 'ampola');
```

## 🎯 Próximos Passos

1. Implementar formulário de cadastro de pacientes
2. Adicionar listagem com paginação
3. Implementar filtros e busca
4. Adicionar gráficos no dashboard
5. Implementar upload de documentos
6. Adicionar impressão de receitas
7. Implementar relatórios em PDF

## 📞 Suporte

Se encontrar problemas:
1. Verifique o console do navegador (F12)
2. Verifique logs do Apache
3. Verifique logs do MySQL
4. Teste os endpoints individualmente no Postman
