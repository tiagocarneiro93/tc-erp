import { Check, X } from 'lucide-react'

import { PASSWORD_RULES } from '@/lib/password-policy'
import { cn } from '@/lib/utils'

interface PasswordRequirementsProps {
  password: string
}

export function PasswordRequirements({ password }: PasswordRequirementsProps) {
  return (
    <ul className="flex flex-col gap-1 text-sm">
      {PASSWORD_RULES.map((rule) => {
        const met = rule.test(password)

        return (
          <li
            key={rule.label}
            className={cn('flex items-center gap-1.5', met ? 'text-green-600 dark:text-green-500' : 'text-destructive')}
          >
            {met ? <Check className="size-3.5" /> : <X className="size-3.5" />}
            {rule.label}
          </li>
        )
      })}
    </ul>
  )
}
