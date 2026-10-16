import { describe, expect, it } from 'vitest'
import { textoExibicao } from './textoExibicao'

describe('textos de apresentação', () => {
  it('concentra os qualificadores no rodapé sem alterar o conteúdo do registro', () => {
    expect(textoExibicao('Bureau A (simulado)')).toBe('Bureau A')
    expect(textoExibicao('Base jurídica fictícia')).toBe('Base jurídica')
    expect(textoExibicao('Registro fictício de processo já concluído.')).toBe('Registro de processo já concluído.')
    expect(textoExibicao('Registro de demonstração sobre divergência contratual.')).toBe('Registro sobre divergência contratual.')
    expect(textoExibicao('Fontes indisponíveis; dados anteriores preservados.')).toBe('Fontes indisponíveis; dados anteriores preservados.')
  })
})
