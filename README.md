JovemConnect
O JovemConnect é uma plataforma inteligente de conexão entre jovens talentos e oportunidades de carreira. O sistema utiliza Inteligência Artificial para calcular a compatibilidade (match) entre o perfil do candidato e os requisitos da vaga.

Tecnologias Utilizadas
Web / Backend Principal
PHP 8.2 (Lógica de negócio e sessões)

MySQL / MariaDB (Banco de Dados)

XAMPP (Servidor Local)

CSS3 (Design Dark Moderno)

Inteligência Artificial
Python 3.10+

Flask (Micro-framework para API)

Flask-CORS (Comunicação entre PHP e Python)

Pandas / Scikit-Learn (Processamento de dados)

Como Instalar e Rodar

1. Banco de Dados
   Abra o phpMyAdmin.

Crie um banco de dados chamado jovemconnect.

Importe o arquivo schema.sql que está na raiz deste projeto.

2. Configuração do PHP
   Mova a pasta do projeto para dentro do htdocs do seu XAMPP.

Certifique-se de que o Apache e o MySQL estão ativos.

Acesse http://localhost/JovemConnect_v2.60/. (Ou versão desejada)

3. Configuração da IA (Flask)
   Navegue até a pasta da IA via terminal.

Crie um ambiente virtual:

Bash
python -m venv venv
source venv/bin/activate # No Windows: venv\Scripts\activate
Instale as dependências:

Bash
pip install -r requirements.txt
Rode o servidor de IA:

Bash
python recomendacao.py
O servidor subirá em http://127.0.0.1:5000

Funcionalidades da IA
Cálculo de Match: Avalia formação técnica, cidade e palavras-chave na descrição da vaga.

Análise de Currículo: Fornece feedbacks em tempo real para o jovem melhorar seu perfil.

Escolaridade Dinâmica: Calcula a idade automaticamente com base na data de nascimento e valida o status do ensino médio.

Estrutura do Projeto
Plaintext
├── /dashboard # Painéis do Jovem e Empresa
├── /jovem # Login, Cadastro e Perfil do Jovem
├── /empresa # Login e Gestão de Vagas
├── /includes # Conexão DB, Header e Footer
├── /assets # CSS, Imagens e JS
├── recomendacao.py # Servidor de IA (Flask)
├── schema.sql # Estrutura do Banco de Dados
└── requirements.txt # Dependências do Python

Autor
Gabriel Sassati - Desenvolvedor Principal
