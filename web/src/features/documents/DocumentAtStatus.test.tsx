import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { postDocumentsAtCommunicationRetry } from '@/api/generated'

import { DocumentAtStatus } from './DocumentAtStatus'

vi.mock('@/api/generated', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/generated')>()),
  postDocumentsAtCommunicationRetry: vi.fn().mockResolvedValue(undefined),
}))

function renderStatus(communication: Parameters<typeof DocumentAtStatus>[0]['communication']) {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <DocumentAtStatus companyId="company-1" documentId="document-1" communication={communication} />
    </QueryClientProvider>,
  )
}

describe('DocumentAtStatus', () => {
  beforeEach(() => {
    vi.mocked(postDocumentsAtCommunicationRetry).mockClear()
  })

  it('says a document without a communication is never communicated', () => {
    renderStatus(null)

    expect(screen.getByTestId('at-status')).toHaveTextContent('não é comunicado')
  })

  it.each([
    ['pending', 'Por comunicar'],
    ['sending', 'A comunicar…'],
    ['accepted', 'Comunicado à AT'],
    ['rejected', 'Rejeitado pela AT'],
    ['failed', 'Falha na comunicação'],
  ] as const)('shows %s as "%s"', (status, label) => {
    renderStatus({ status, attempts: 0, can_retry: false })

    expect(screen.getByText(label)).toBeInTheDocument()
  })

  it("shows the AT's own answer and the attempt count", () => {
    renderStatus({
      status: 'rejected',
      attempts: 2,
      response_code: '-16',
      response_message: 'Nif inválido',
      can_retry: true,
    })

    expect(screen.getByText('-16: Nif inválido')).toBeInTheDocument()
    expect(screen.getByText('2 tentativas')).toBeInTheDocument()
  })

  it('offers no retry unless the backend says one would be accepted', () => {
    renderStatus({ status: 'failed', attempts: 3, can_retry: false })

    expect(screen.queryByRole('button', { name: 'Tentar novamente' })).not.toBeInTheDocument()
  })

  it('retries with an Idempotency-Key when asked to', async () => {
    renderStatus({ status: 'failed', attempts: 3, can_retry: true })

    await userEvent.click(screen.getByRole('button', { name: 'Tentar novamente' }))

    await waitFor(() => expect(postDocumentsAtCommunicationRetry).toHaveBeenCalledTimes(1))
    expect(postDocumentsAtCommunicationRetry).toHaveBeenCalledWith('company-1', 'document-1', {
      headers: { 'Idempotency-Key': expect.stringMatching(/^[0-9a-f-]{36}$/) as string },
    })
  })

  it('reports a refused retry', async () => {
    const { ApiError } = await import('@/api/http-client')
    vi.mocked(postDocumentsAtCommunicationRetry).mockRejectedValueOnce(new ApiError(422, {}))
    renderStatus({ status: 'failed', attempts: 3, can_retry: true })

    await userEvent.click(screen.getByRole('button', { name: 'Tentar novamente' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('não tem uma comunicação por repetir')
  })
})
