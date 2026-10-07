import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetDocumentsGetQueryOptions,
  postDocumentsAtCommunicationRetry,
  type GetDocumentsGet200AtCommunication,
  type GetDocumentsGet200AtCommunicationStatus,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { apiErrorMessage } from '@/lib/api-error'

const STATUS: Record<
  GetDocumentsGet200AtCommunicationStatus,
  { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
  pending: { label: 'Por comunicar', variant: 'secondary' },
  sending: { label: 'A comunicar…', variant: 'secondary' },
  accepted: { label: 'Comunicado à AT', variant: 'default' },
  rejected: { label: 'Rejeitado pela AT', variant: 'destructive' },
  failed: { label: 'Falha na comunicação', variant: 'destructive' },
}

/**
 * docs/plans/phase-3.md task 3.8: where the document stands with the AT
 * (technical-scope.md §7.5's outbox, read model from task 3.2). `null` is
 * what the backend sends for documents that are never communicated (training
 * series). Whether a retry would currently be accepted comes from the backend
 * (`can_retry`) — eligibility is not re-derived here.
 */
export function DocumentAtStatus({
  companyId,
  documentId,
  communication,
}: {
  companyId: string
  documentId: string
  communication: GetDocumentsGet200AtCommunication | null | undefined
}) {
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)

  const retry = useMutation<void, ApiError>({
    mutationFn: () =>
      postDocumentsAtCommunicationRetry(companyId, documentId, { headers: { 'Idempotency-Key': crypto.randomUUID() } }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: getGetDocumentsGetQueryOptions(companyId, documentId).queryKey }),
    onError: (e) =>
      setError(
        apiErrorMessage(e, {
          403: 'Sem permissão para voltar a comunicar documentos.',
          422: 'Este documento não tem uma comunicação por repetir.',
        }),
      ),
  })

  if (!communication?.status) {
    return (
      <p className="text-muted-foreground text-sm" data-testid="at-status">
        Autoridade Tributária: este documento não é comunicado (série de formação).
      </p>
    )
  }

  const { label, variant } = STATUS[communication.status]

  return (
    <div className="flex flex-col gap-2 rounded-md border p-3" data-testid="at-status">
      <div className="flex items-center gap-3">
        <span className="text-sm font-medium">Autoridade Tributária</span>
        <Badge variant={variant}>{label}</Badge>
        {communication.can_retry && (
          <Button
            variant="outline"
            size="sm"
            disabled={retry.isPending}
            onClick={() => {
              setError(null)
              retry.mutate()
            }}
          >
            {retry.isPending ? 'A pedir…' : 'Tentar novamente'}
          </Button>
        )}
      </div>
      {communication.response_message && (
        <p className="text-muted-foreground text-sm">
          {communication.response_code ? `${communication.response_code}: ` : ''}
          {communication.response_message}
        </p>
      )}
      {(communication.attempts ?? 0) > 0 && (
        <p className="text-muted-foreground text-xs">
          {communication.attempts === 1 ? '1 tentativa' : `${communication.attempts} tentativas`}
        </p>
      )}
      {error && (
        <p className="text-destructive text-sm" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
