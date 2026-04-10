'use client'

import { KanbanCard } from '@/lib/types'
import { 
  Clock, 
  MapPin, 
  User, 
  Building2, 
  AlertCircle, 
  MoreVertical 
} from 'lucide-react'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'

interface CardProps {
  card: KanbanCard
}

export default function KanbanCardItem({ card }: CardProps) {
  const priorityColors = {
    baixa: 'bg-green-500/20 text-green-500 border-green-500/30',
    media: 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
    alta: 'bg-red-500/20 text-red-500 border-red-500/30',
  }

  return (
    <div className="glass-dark p-4 rounded-xl border border-white/5 hover:border-blue-500/30 transition-all cursor-grab active:cursor-grabbing group shadow-lg shadow-black/20">
      <div className="flex items-start justify-between mb-3">
        <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded-full border ${priorityColors[card.priority]}`}>
          {card.priority}
        </span>
        <button className="text-gray-500 hover:text-white transition-colors">
          <MoreVertical size={16} />
        </button>
      </div>

      <h4 className="text-white font-semibold text-sm mb-2 group-hover:text-blue-400 transition-colors line-clamp-2">
        {card.title}
      </h4>

      {card.description && (
        <p className="text-gray-500 text-xs mb-4 line-clamp-2 italic">
          {card.description}
        </p>
      )}

      <div className="space-y-2">
        {card.company_name && (
          <div className="flex items-center gap-2 text-gray-400 text-[11px]">
            <Building2 size={12} className="text-blue-500/50" />
            <span className="truncate">{card.company_name}</span>
          </div>
        )}
        {card.client_name && (
          <div className="flex items-center gap-2 text-gray-400 text-[11px]">
            <User size={12} className="text-blue-500/50" />
            <span className="truncate">{card.client_name}</span>
          </div>
        )}
      </div>

      <div className="h-px bg-white/5 my-3" />

      <div className="flex items-center justify-between text-[10px] text-gray-500">
        <div className="flex items-center gap-1">
          <Clock size={12} />
          <span>{format(new Date(card.created_at), "dd MMM", { locale: ptBR })}</span>
        </div>
        {card.tracking_code && (
          <div className="flex items-center gap-1 text-blue-400/80 bg-blue-500/10 px-1.5 py-0.5 rounded-md border border-blue-500/20">
            <MapPin size={10} />
            <span className="font-medium">{card.tracking_code}</span>
          </div>
        )}
      </div>
    </div>
  )
}
