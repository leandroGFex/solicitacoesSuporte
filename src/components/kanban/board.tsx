'use client'

import { useState, useEffect } from 'react'
import { KanbanCard, KanbanColumn, CardCategory } from '@/lib/types'
import { getColumns, getCards } from '@/lib/kanban-actions'
import KanbanCardItem from './card'
import { 
  Plus, 
  MoreHorizontal, 
  LayoutColumns, 
  Settings2, 
  ArrowRightLeft,
  Search,
  SlidersHorizontal 
} from 'lucide-react'

interface BoardProps {
  category: CardCategory
}

export default function KanbanBoard({ category }: BoardProps) {
  const [columns, setColumns] = useState<KanbanColumn[]>([])
  const [cards, setCards] = useState<KanbanCard[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function loadData() {
      try {
        const [cols, cds] = await Promise.all([
          getColumns(category),
          getCards(category)
        ])
        setColumns(cols)
        setCards(cds)
      } catch (error) {
        console.error('Falha ao carregar dados do Kanban:', error)
      } finally {
        setLoading(false)
      }
    }
    loadData()
  }, [category])

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500" />
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full space-y-6">
      {/* Topo do Board / Filtros */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div className="relative group">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500 group-focus-within:text-blue-500 transition-colors" />
            <input 
              type="text" 
              placeholder="Filtrar nesta categoria..." 
              className="bg-white/5 border border-white/10 rounded-xl py-2 pl-10 pr-4 text-sm text-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/50 transition-all w-64"
            />
          </div>
          <button className="flex items-center gap-2 p-2 px-4 rounded-xl text-gray-400 bg-white/5 hover:text-white transition-colors text-sm border border-white/10 cursor-pointer">
            <SlidersHorizontal size={16} />
            Filtros Avançados
          </button>
        </div>

        <div className="flex items-center gap-3">
          <button className="text-gray-400 hover:text-white transition-colors p-2 cursor-pointer">
            <LayoutColumns size={18} />
          </button>
          <button className="text-gray-400 hover:text-white transition-colors p-2 cursor-pointer">
            <Settings2 size={18} />
          </button>
        </div>
      </div>

      {/* Árez do Board */}
      <div className="flex flex-1 gap-6 overflow-x-auto pb-6 custom-scrollbar items-start">
        {columns.map((column) => (
          <div 
            key={column.id} 
            className="flex-shrink-0 w-[320px] bg-black/20 rounded-2xl border border-white/5 flex flex-col max-h-full"
          >
            {/* Header da Coluna */}
            <div className="p-4 flex items-center justify-between border-b border-white/5">
              <div className="flex items-center gap-2">
                <div 
                  className="w-2.5 h-2.5 rounded-full" 
                  style={{ backgroundColor: column.color }} 
                />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                  {column.name}
                </h3>
                <span className="text-[10px] font-bold bg-white/5 text-gray-400 px-2 py-0.5 rounded-lg border border-white/10">
                  {cards.filter(c => c.column_id === column.id).length}
                </span>
              </div>
              <div className="flex items-center gap-1">
                <button className="text-gray-500 hover:text-blue-400 transition-colors p-1 cursor-pointer">
                  <Plus size={16} />
                </button>
                <button className="text-gray-500 hover:text-gray-300 transition-colors p-1 cursor-pointer">
                  <MoreHorizontal size={16} />
                </button>
              </div>
            </div>

            {/* Cards da Coluna */}
            <div className="flex-1 overflow-y-auto p-3 space-y-4 min-h-[500px] custom-scrollbar">
              {cards
                .filter((card) => card.column_id === column.id)
                .map((card) => (
                  <KanbanCardItem key={card.id} card={card} />
                ))}
            </div>

            {/* Footer da Coluna (Opcional) */}
            <div className="p-3">
               <button className="w-full py-2 hover:bg-white/5 rounded-xl text-xs font-semibold text-gray-500 hover:text-white transition-all flex items-center justify-center gap-2 group border border-dashed border-white/10 hover:border-white/20 cursor-pointer">
                 <Plus size={14} className="group-hover:scale-125 transition-transform" />
                 Adicionar Solicitação
               </button>
            </div>
          </div>
        ))}

        {/* Botão de Adicionar Coluna (Admin) */}
        <button className="flex-shrink-0 w-80 h-16 rounded-2xl border-2 border-dashed border-white/5 hover:border-white/20 flex items-center justify-center gap-3 text-gray-600 hover:text-gray-400 transition-all group cursor-pointer">
           <Plus size={20} className="group-hover:rotate-90 transition-transform duration-300" />
           <span className="font-bold text-sm">Adicionar Nova Coluna</span>
        </button>
      </div>
    </div>
  )
}
