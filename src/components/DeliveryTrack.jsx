import React from 'react';
import { User, ShieldCheck, Inbox, Mail } from 'lucide-react';

/* The "where is my message" animation after a form is sent: the message flies from the visitor to the head of
   the team that handles it, is received there, and the reply sets off back to the visitor's inbox. CSS: dt-* in
   src/index.css (respects reduced motion). */
export const DEPARTMENTS = {
  contact: 'Head of Support',
  support: 'Head of Support',
  'write-for-us': 'Head of Editorial',
  partnership: 'Head of Partnerships',
  suggestion: 'Head of Product',
  comment: 'Head of Community',
};

export const DeliveryTrack = ({ formType = 'contact' }) => {
  const head = DEPARTMENTS[formType] || DEPARTMENTS.contact;
  return (
    <div className="dt" role="img" aria-label={'Your message went to our ' + head + '; the reply will come to your inbox.'}>
      <div className="dt-line" />
      <div className="dt-st dt-a"><span className="dt-ic"><User /></span><b>You</b><small>Message sent</small></div>
      <div className="dt-st dt-b"><span className="dt-ic"><ShieldCheck /></span><b>{head}</b><small>Received</small></div>
      <div className="dt-st dt-c"><span className="dt-ic"><Inbox /></span><b>Your inbox</b><small>Reply on its way</small></div>
      <span className="dt-env dt-go"><Mail /></span>
      <span className="dt-env dt-back"><Mail /></span>
    </div>
  );
};
