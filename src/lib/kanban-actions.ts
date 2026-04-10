import { createClient } from '@/lib/supabase/client'
import { KanbanCard, KanbanColumn, CardCategory } from './types'

export async function getColumns(category: CardCategory = 'cartao'): Promise<KanbanColumn[]> {
  const supabase = createClient()
  const { data, error } = await supabase
    .from('columns_kanban')
    .select('*')
    .eq('category', category)
    .order('position', { ascending: true })

  if (error) throw error
  return data
}

export async function getCards(category: CardCategory = 'cartao'): Promise<KanbanCard[]> {
  const supabase = createClient()
  const { data, error } = await supabase
    .from('cards')
    .select('*')
    .eq('category', category)
    .eq('is_archived', false)
    .order('position', { ascending: true })

  if (error) throw error
  return data
}

export async function updateCardPosition(cardId: number, columnId: number, position: number) {
  const supabase = createClient()
  const { error } = await supabase
    .from('cards')
    .update({ 
      column_id: columnId, 
      position,
      updated_at: new Date().toISOString()
    })
    .eq('id', cardId)

  if (error) throw error
}
