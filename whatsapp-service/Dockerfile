# imagem base Node 20 (requisito: Node >= 20)
FROM node:20-alpine

WORKDIR /app

ENV NODE_ENV=production

# Copiar manifesto e instalar dependencias de producao.
# pdf-to-printer e opcional (optionalDependencies): se falhar a instalacao, continua.
COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund

# Codigo da aplicacao.
COPY src ./src

# Diretorio de spool para trabalhos de impressao.
RUN mkdir -p /app/spool && chown -R node:node /app

USER node

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||3000)+'/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "src/index.js"]
