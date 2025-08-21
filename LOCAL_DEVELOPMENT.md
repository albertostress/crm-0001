# 🏠 EspoCRM Local Development - EVERTEC Branding

## 🎯 **Como Usar o Ambiente Local**

### 🚀 **1. Iniciar Ambiente Local**

```bash
# Navegar para o diretório do projeto
cd /mnt/d/Projecto/Kwame/espocrm

# Iniciar containers locais
docker-compose -f docker-compose.local.yml up -d

# Acompanhar logs
docker-compose -f docker-compose.local.yml logs -f espocrm-local
```

### 🌐 **2. Acessar Aplicação**

- **EspoCRM:** http://localhost:8080
- **phpMyAdmin:** http://localhost:8081
- **Database:** localhost:3307

### 🔧 **3. Credenciais**

```
# EspoCRM Admin
Username: admin
Password: admin123

# Database
Host: localhost:3307
User: espocrm
Password: espocrm123
Database: espocrm

# Root MySQL
User: root
Password: root123
```

---

## ✏️ **EDIÇÃO E TESTES EM TEMPO REAL**

### 📁 **Arquivos Editáveis (Bind Mount):**

```
client/custom/res/css/custom.css     ← Editar CSS
client/custom/lib/custom-footer.js   ← Editar JavaScript
custom/Espo/Custom/...               ← Editar Metadata
```

### 🔄 **Workflow de Desenvolvimento:**

1. **Editar arquivos** localmente
2. **Salvar** (mudanças refletem instantaneamente)
3. **Testar** no navegador (F5)
4. **Ajustar** conforme necessário
5. **Repetir** até perfeito

### ⚡ **Comandos Úteis:**

```bash
# Rebuild EspoCRM (dentro do container)
docker exec espocrm-local php /var/www/html/bin/command rebuild

# Clear cache
docker exec espocrm-local php /var/www/html/bin/command clear-cache

# Restart container
docker restart espocrm-local

# Entrar no container
docker exec -it espocrm-local bash

# Ver logs em tempo real
docker logs -f espocrm-local
```

---

## 🎨 **TESTE E REFINAMENTO DO BRANDING**

### ✅ **Locais para Verificar:**

1. **Página de Login** - Footer
2. **Dashboard Principal** - Sidebar/Footer
3. **Configurações** - Footer
4. **Modais/Popups** - Footer
5. **Páginas de Erro** - Footer

### 🔧 **Se o Watermark Ainda Aparecer:**

#### **1. Editar CSS (mais CSS rules):**
```bash
# Editar
code client/custom/res/css/custom.css

# Adicionar regra específica
.novo-seletor { display: none !important; }
```

#### **2. Editar JavaScript (mais agressivo):**
```bash
# Editar
code client/custom/lib/custom-footer.js

# Adicionar novos seletores
const moreSelectors = ['.novo-elemento'];
```

#### **3. Aplicar mudanças:**
```bash
# Rebuild
docker exec espocrm-local php /var/www/html/bin/command rebuild

# Clear cache
docker exec espocrm-local rm -rf /var/www/html/data/cache/*

# Restart
docker restart espocrm-local
```

---

## 🕵️ **DEBUG E INSPEÇÃO**

### 🔍 **Encontrar Elementos com Watermark:**

1. **F12** → Elements
2. **Ctrl+F** → Procurar "EspoCRM"
3. **Identificar** classes/IDs
4. **Adicionar** nos seletores

### 📝 **Verificar Arquivos no Container:**

```bash
# Verificar se CSS existe
docker exec espocrm-local cat /var/www/html/client/custom/res/css/custom.css

# Verificar se JS existe
docker exec espocrm-local ls -la /var/www/html/client/custom/lib/

# Verificar metadata
docker exec espocrm-local cat /var/www/html/custom/Espo/Custom/Resources/metadata/app/client.json

# Procurar por EspoCRM em arquivos
docker exec espocrm-local grep -r "EspoCRM" /var/www/html/client/ | head -10
```

### 🎯 **Testes de Funcionamento:**

```javascript
// No console do navegador (F12)
console.log('Custom CSS loaded:', !!document.querySelector('link[href*="custom.css"]'));
console.log('Custom JS loaded:', !!window.evertecBrandingStatus);

// Forçar execução
if (window.evertecBrandingStatus) {
    console.log('Status:', window.evertecBrandingStatus());
}
```

---

## 🚀 **DEPLOY PARA DOKPLOY**

### ✅ **Quando Tudo Estiver Funcionando Local:**

```bash
# 1. Parar ambiente local
docker-compose -f docker-compose.local.yml down

# 2. Commit mudanças
git add -A
git commit -m "Perfect EVERTEC branding - tested locally"

# 3. Push para GitHub
git push origin master

# 4. Redeploy no Dokploy
# (Vai usar a versão testada e funcionando)
```

---

## 🔄 **COMANDOS DE GESTÃO**

### **Iniciar:**
```bash
docker-compose -f docker-compose.local.yml up -d
```

### **Parar:**
```bash
docker-compose -f docker-compose.local.yml down
```

### **Rebuild:**
```bash
docker-compose -f docker-compose.local.yml build --no-cache
docker-compose -f docker-compose.local.yml up -d
```

### **Reset Completo:**
```bash
docker-compose -f docker-compose.local.yml down -v
docker-compose -f docker-compose.local.yml up -d
```

---

## 📊 **Vantagens desta Abordagem:**

✅ **Desenvolvimento Rápido** - Mudanças instantâneas  
✅ **Debug Eficiente** - Logs e inspeção local  
✅ **Testes Completos** - Verificar todos os cenários  
✅ **Deploy Confiável** - Só enviar o que funciona  
✅ **Iteração Rápida** - Ajustes em segundos  

---

**🎉 AGORA você pode testar e refinar o branding localmente até ficar perfeito!**

*Depois é só fazer push da versão final para o Dokploy!*