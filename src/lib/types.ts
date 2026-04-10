export type CardCategory = 'cartao' | 'tag' | 'pos' | 'rastreador'

export interface KanbanColumn {
  id: number
  name: string
  color: string
  icon: string
  position: number
  category: CardCategory
}

export interface KanbanCard {
  id: number
  column_id: number | null
  title: string
  description: string | null
  category: CardCategory
  company_name: string | null
  client_name: string | null
  client_email: string | null
  cnpj: string | null
  tracking_code: string | null
  tracking_status: string | null
  priority: 'baixa' | 'media' | 'alta'
  position: number
  is_archived: boolean
  created_at: string
  updated_at: string
}
