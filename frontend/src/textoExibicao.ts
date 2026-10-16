// Os dados originais continuam intactos na API e no banco.
// A identificação de demonstração permanece nos rodapés da aplicação e do PDF.
export function textoExibicao(texto: string): string {
  return texto
    .replace(/\s*\((?:simulad[oa]|fict[ií]ci[oa])\)/gi, '')
    .replace(/\s+(?:fict[ií]ci[oa]s?|de demonstração)\b/gi, '')
}
