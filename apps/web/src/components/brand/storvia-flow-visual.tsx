import * as React from "react"

import { cn } from "@/lib/utils"

function StorviaFlowVisual({
  className,
  ...props
}: React.ComponentProps<"svg">) {
  return (
    <svg
      viewBox="0 0 620 420"
      fill="none"
      aria-hidden="true"
      className={cn("h-auto w-full", className)}
      {...props}
    >
      <g opacity="0.55" className="stroke-border">
        <path d="M54 74H566" strokeWidth="1" strokeDasharray="4 10" />
        <path d="M54 346H566" strokeWidth="1" strokeDasharray="4 10" />
        <path d="M94 42V378" strokeWidth="1" strokeDasharray="4 10" />
        <path d="M526 42V378" strokeWidth="1" strokeDasharray="4 10" />
      </g>

      <g className="stroke-brand-blue">
        <path
          d="M94 246C156 246 155 142 230 142C302 142 304 240 379 240C447 240 458 156 526 156"
          strokeWidth="3"
          strokeLinecap="round"
        />
        <path
          d="M94 288C158 288 176 326 240 326C310 326 327 275 391 275C456 275 476 306 526 306"
          strokeWidth="1.5"
          strokeLinecap="round"
          opacity="0.42"
        />
      </g>

      <g className="stroke-brand-cyan">
        <path
          d="M94 190C154 190 176 92 246 92C318 92 331 184 405 184C462 184 487 126 526 126"
          strokeWidth="1.75"
          strokeLinecap="round"
          opacity="0.9"
        />
      </g>

      <g className="fill-card stroke-border">
        <rect x="190" y="154" width="244" height="146" rx="22" strokeWidth="1.4" />
        <rect x="218" y="184" width="94" height="88" rx="16" strokeWidth="1.2" />
        <rect x="329" y="184" width="77" height="18" rx="9" strokeWidth="1.2" />
        <rect x="329" y="216" width="55" height="12" rx="6" strokeWidth="1.2" />
        <rect x="329" y="244" width="64" height="12" rx="6" strokeWidth="1.2" />
      </g>

      <g className="fill-brand-ice stroke-brand-blue">
        <path
          d="M235 211H277L286 220V253C286 259.6 280.6 265 274 265H235C228.4 265 223 259.6 223 253V223C223 216.4 228.4 211 235 211Z"
          strokeWidth="1.5"
        />
        <path d="M277 211V221H286" strokeWidth="1.5" strokeLinejoin="round" />
      </g>

      <g className="fill-background stroke-border">
        <rect x="66" y="170" width="72" height="50" rx="14" strokeWidth="1.2" />
        <rect x="484" y="228" width="72" height="50" rx="14" strokeWidth="1.2" />
        <rect x="452" y="84" width="74" height="48" rx="14" strokeWidth="1.2" />
      </g>

      <g className="fill-brand-blue">
        <circle cx="94" cy="190" r="5" />
        <circle cx="526" cy="156" r="5" />
        <circle cx="526" cy="306" r="4.5" />
      </g>

      <g className="fill-brand-cyan">
        <circle cx="94" cy="246" r="4" />
        <circle cx="526" cy="126" r="4.5" />
      </g>

      <g className="fill-brand-navy dark:fill-brand-blue">
        <rect x="84" y="186" width="20" height="8" rx="4" />
        <rect x="500" y="248" width="20" height="8" rx="4" />
        <rect x="474" y="104" width="28" height="8" rx="4" />
      </g>

      <g className="stroke-brand-cyan" opacity="0.6">
        <circle cx="311" cy="227" r="116" strokeWidth="1.2" strokeDasharray="5 11" />
        <circle cx="311" cy="227" r="144" strokeWidth="0.8" strokeDasharray="2 14" />
      </g>
    </svg>
  )
}

export { StorviaFlowVisual }
