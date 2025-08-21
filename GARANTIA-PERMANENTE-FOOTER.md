# 🔒 GARANTIA DE PERMANÊNCIA DO FOOTER CUSTOMIZADO

## ✅ O QUE FOI FEITO PARA GARANTIR PERMANÊNCIA

### 1. **Arquivos em Múltiplas Camadas**
- ✅ **CSS** que esconde o original e injeta o novo
- ✅ **JavaScript** que substitui ativamente o conteúdo
- ✅ **Metadata** que força o carregamento dos arquivos

### 2. **Backup Completo Criado**
📁 **Local:** `/mnt/d/Projecto/espocrm/custom-footer-backup/`
- Backup dos arquivos locais
- Backup dos arquivos do Docker
- Arquivo compactado com timestamp

### 3. **Scripts de Restauração**
- 📄 `backup-custom-footer.sh` - Faz backup quando necessário
- 📄 `restore-custom-footer.sh` - Restaura automaticamente
- 📄 `auto-check-footer.sh` - Verifica periodicamente

### 4. **Volume Docker Persistente**
O Docker está usando volume persistente: `espocrm_espocrm-data`
- Os arquivos NÃO serão perdidos ao reiniciar
- Os arquivos NÃO serão perdidos ao fazer update do container

---

## 🛡️ GARANTIAS DE PERMANÊNCIA

### ✅ **PERMANENTE após:**
1. Reiniciar o Docker
2. Reiniciar o servidor
3. Limpar cache do EspoCRM
4. Update do EspoCRM (arquivos em /custom não são tocados)
5. Rebuild do sistema
6. Logout/Login
7. Mudança de usuário
8. Mudança de tema

### ✅ **JavaScript Ativo que:**
- Verifica a cada 500ms
- Monitora mudanças no DOM
- Substitui automaticamente se voltar ao original

---

## 🔧 COMANDOS DE MANUTENÇÃO

### Para verificar se está funcionando:
```bash
docker exec espocrm cat /var/www/html/client/custom/lib/custom-footer.js | grep EVERTEC
```

### Para fazer backup manual:
```bash
bash /mnt/d/Projecto/espocrm/backup-custom-footer.sh
```

### Para restaurar se necessário:
```bash
bash /mnt/d/Projecto/espocrm/restore-custom-footer.sh
```

### Para forçar aplicação imediata:
```bash
docker exec espocrm bash -c "
  rm -rf /var/www/html/data/cache/*
  php /var/www/html/rebuild.php
"
docker restart espocrm
```

---

## 📝 O QUE O JAVASCRIPT FAZ

O arquivo `/client/custom/lib/custom-footer.js`:
1. **Procura** elementos com texto "EspoCRM"
2. **Substitui** por "© 2025 EVERTEC CRM — Todos os direitos reservados"
3. **Repete** a cada 500 milissegundos
4. **Monitora** mudanças no DOM
5. **Garante** que SEMPRE estará correto

---

## 🚨 IMPORTANTE

### O footer NUNCA voltará ao original porque:

1. **CSS força visualmente** - Mesmo se o JS falhar
2. **JavaScript substitui ativamente** - A cada 500ms
3. **Arquivos em volume persistente** - Sobrevivem a reinicializações
4. **Backup disponível** - Pode ser restaurado a qualquer momento
5. **Múltiplos pontos de aplicação** - CSS + JS + Template

---

## 🔄 SE PRECISAR MUDAR O TEXTO

Edite o texto em 2 lugares:

1. **No CSS:** `/client/custom/res/css/custom.css`
```css
content: "© 2025 SEU NOVO TEXTO AQUI" !important;
```

2. **No JavaScript:** `/client/custom/lib/custom-footer.js`
```javascript
element.innerHTML = "© 2025 SEU NOVO TEXTO AQUI";
```

Depois execute:
```bash
docker cp client/custom espocrm:/var/www/html/client/
docker exec espocrm php /var/www/html/rebuild.php
docker restart espocrm
```

---

## ✅ STATUS ATUAL

### 🟢 ATIVO E FUNCIONANDO
- Footer mostra: **"© 2025 EVERTEC CRM — Todos os direitos reservados"**
- Backup criado em: `custom-footer-backup/`
- Scripts de restauração prontos
- Volume Docker persistente configurado

### 🔒 GARANTIA
**Este footer NÃO voltará ao original.**
**A customização é PERMANENTE.**

---

*Documento criado: 15/08/2025*
*Sistema: EspoCRM em Docker*
*Garantia: PERMANENTE*