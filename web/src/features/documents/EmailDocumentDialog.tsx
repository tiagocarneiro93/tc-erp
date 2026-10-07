import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'

import { postDocumentsSend } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api-error'

/**
 * docs/plans/phase-3.md task 3.8 on top of task 3.7: "Enviar por email".
 * The backend queues the sending (202) and makes the sealed PDF once; an
 * empty recipient list means "the customer's own address", resolved there.
 */
export function EmailDocumentDialog({
  companyId,
  documentId,
  open,
  onOpenChange,
}: {
  companyId: string
  documentId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const [recipients, setRecipients] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [queued, setQueued] = useState(false)

  const send = useMutation<void, ApiError>({
    mutationFn: () =>
      postDocumentsSend(
        companyId,
        documentId,
        {
          recipients: recipients
            .split(/[,;\s]+/)
            .map((address) => address.trim())
            .filter((address) => '' !== address),
          message: '' === message.trim() ? null : message.trim(),
        },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      ),
    onSuccess: () => setQueued(true),
    onError: (e) =>
      setError(
        apiErrorMessage(e, {
          403: 'Sem permissão para enviar documentos.',
          404: 'Documento não encontrado.',
          422: 'Indique pelo menos um endereço de email válido (o cliente não tem email registado).',
        }),
      ),
  })

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setQueued(false)
      setError(null)
    }

    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Enviar por email</DialogTitle>
        </DialogHeader>
        {queued ? (
          <div className="flex flex-col gap-4">
            <p role="status">Envio agendado. O documento selado será enviado em instantes.</p>
            <Button onClick={() => handleOpenChange(false)}>Fechar</Button>
          </div>
        ) : (
          <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
              event.preventDefault()
              setError(null)
              send.mutate()
            }}
          >
            <div className="flex flex-col gap-2">
              <Label htmlFor="email-recipients">Destinatários</Label>
              <Input
                id="email-recipients"
                value={recipients}
                onChange={(event) => setRecipients(event.target.value)}
                placeholder="Em branco: o email do cliente"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="email-message">Mensagem (opcional)</Label>
              <Input
                id="email-message"
                value={message}
                maxLength={2000}
                onChange={(event) => setMessage(event.target.value)}
              />
            </div>
            {error && (
              <p className="text-destructive text-sm" role="alert">
                {error}
              </p>
            )}
            <Button type="submit" disabled={send.isPending}>
              {send.isPending ? 'A enviar…' : 'Enviar'}
            </Button>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}
